#!/usr/bin/env bash
# Run only against a dedicated, empty local test database.
set -euo pipefail
[[ "${DB_DATABASE:-}" == haleef_test && "${DB_HOST:-}" == 127.0.0.1 ]]
tmp="$(mktemp -d)"
base=http://127.0.0.1:8097
export PUBLIC_STORAGE_PATH="$tmp/public" PRIVATE_STORAGE_PATH="$tmp/private"
php bin/migrate.php
php bin/migrate.php
BREVO_API_KEY=fixture CONTACT_EMAIL_FROM=verified@example.test AGENDA_EMAIL_FROM=agenda@example.test php -r 'require "includes/config.php"; $input=["name"=>"Test Visitor","email"=>"visitor@example.test","company"=>"Example Org","type"=>"Training","location"=>"Dubai","message"=>"Please tell me about training."]; $assert=static function ($key,$mail) { if ($key!=="fixture" || $mail["sender"]["email"]!=="verified@example.test" || $mail["to"]!==[["email"=>"team@haleefglobal.com"]] || $mail["replyTo"]!==["email"=>"visitor@example.test"] || !str_contains($mail["textContent"],"Please tell me about training.")) throw new RuntimeException("Wrong contact email payload"); }; Haleef\Support\ContactEmail::send($input,SITE_EMAIL,$assert); putenv("CONTACT_EMAIL_FROM="); Haleef\Support\ContactEmail::send($input,SITE_EMAIL,static function ($key,$mail) { if ($mail["sender"]["email"]!=="agenda@example.test") throw new RuntimeException("Sender fallback failed"); });'
php -r 'require "includes/bootstrap.php"; use Haleef\Training\Editorial; foreach ([["Europe/Berlin","2027-02-10","2027-02-11","CET"],["Europe/Berlin","2027-07-10","2027-07-11","CEST"],["Europe/Berlin","2027-03-27","2027-03-29","CET / CEST"],["Etc/UTC","2027-07-10","2027-07-11","UTC"],["America/New_York","2027-07-10","2027-07-11","EDT"],["America/Los_Angeles","2027-02-10","2027-02-11","PST"],["Asia/Dubai","2027-07-10","2027-07-11","GST"]] as [$timezone,$start,$end,$expected]) { if (Editorial::timezoneLabel(["timezone"=>$timezone,"starts_at"=>$start." 09:00:00","ends_at"=>$end." 09:00:00"]) !== $expected) throw new RuntimeException("Wrong time zone label for $timezone"); }'
php -r 'require "includes/bootstrap.php"; use Haleef\Support\MoneyDisplay; foreach ([["USD",10,"US$10.00"],["EUR",1199,"€1,199.00"],["GBP",-1.5,"-£1.50"],["AED",120,"120.00\u{00A0}\u{2067}د.إ\u{2069}"],["CHF",10,"CHF 10.00"]] as [$code,$amount,$expected]) { if (MoneyDisplay::format($amount,$code) !== $expected) throw new RuntimeException("Wrong display for $code"); }'
php -r 'require "includes/bootstrap.php"; $html=Haleef\Training\Markdown::render("**safe**\n\n- <img src=x>\n- [unsafe](javascript:alert(1))"); if (!str_contains($html,"<strong>safe</strong>") || !str_contains($html,"<ul>") || str_contains($html,"<img") || str_contains($html,"href=")) exit(1);'
php -r 'require "includes/bootstrap.php"; Haleef\Support\Database::connection()->prepare("INSERT INTO admin_users(name,email,password_hash,role) VALUES (?,?,?,?)")->execute(["Smoke","operator@example.test",password_hash("smoke-only-password",PASSWORD_DEFAULT),"administrator"]);'
AGENDA_EMAIL_ENABLED=1 BREVO_API_KEY=smoke-never-sent AGENDA_EMAIL_FROM=sender@example.test php -S 127.0.0.1:8097 router.php >"$tmp/server.log" 2>&1 &
server=$!
trap 'code=$?; kill "$server" 2>/dev/null || true; if ((code)); then tail -80 "$tmp/server.log"; head -c 1200 "$tmp/body" || true; fi; exit "$code"' EXIT
for i in {1..20}; do
    if curl -fsS "$base/health" >/dev/null 2>&1; then break; fi
    sleep .25
done
get() { curl -fsS -b "$tmp/$1" -c "$tmp/$1" "$base$2"; }
csrf() { grep -o 'name="_csrf" value="[a-f0-9]*"' | sed -n '1p' | cut -d '"' -f4; }
status() { curl -sS -o "$tmp/body" -w '%{http_code}' "$base$1"; }
get visitor / >"$tmp/home"
grep -Fq 'mailto:team@haleefglobal.com' "$tmp/home"
grep -Fq 'mailto:ask@haleeflearning.com' "$tmp/home"
if grep -Fq 'mailto:hello@thehaleef.com' "$tmp/home"; then exit 1; fi
for page in privacy terms contact; do
    get visitor "/$page" >"$tmp/contact-address-check"
    [[ "$(grep -oF 'mailto:team@haleefglobal.com' "$tmp/contact-address-check" | wc -l)" -eq 2 ]]
    if grep -Fqi 'hello@thehaleef.com' "$tmp/contact-address-check"; then exit 1; fi
done
if grep -Fq 'class="whatsapp-contact"' "$tmp/home"; then exit 1; fi
printf 'WHATSAPP_NUMBER=12025550123\n' >"$tmp/whatsapp.env"
WHATSAPP_NUMBER=12025550124 php -r 'require "app/Autoload.php"; Haleef\Support\Env::load($argv[1]); require "includes/config.php"; require "includes/whatsapp-widget.php";' "$tmp/whatsapp.env" >"$tmp/widget"
grep -Fq 'href="https://wa.me/12025550123"' "$tmp/widget"
printf 'WHATSAPP_NUMBER=+invalid\n' >"$tmp/whatsapp.env"
php -r 'require "app/Autoload.php"; Haleef\Support\Env::load($argv[1]); require "includes/config.php"; require "includes/whatsapp-widget.php";' "$tmp/whatsapp.env" >"$tmp/widget"
test ! -s "$tmp/widget"
get visitor /training >"$tmp/no-agenda"
grep -Fq 'Haleef Global Learning Programs' "$tmp/no-agenda"
if grep -Fq 'href="#agenda"' "$tmp/no-agenda"; then exit 1; fi
submit() {
    local cookie=$1 path=$2 expected=$3 actual
    shift 3
    actual="$(curl -sS -D "$tmp/headers" -b "$tmp/$cookie" -c "$tmp/$cookie" -o "$tmp/body" -w '%{http_code}' "$@" "$base$path")"
    if [[ "$actual" != "$expected" ]]; then
        printf 'Expected %s, got %s from %s\n' "$expected" "$actual" "$path" >&2
        grep -Eo 'admin-alert-error[^<]*|form-message error[^<]*' "$tmp/body" || true
        return 1
    fi
}
token="$(get admin /admin/login | csrf)"
get visitor /contact >"$tmp/contact-form"
contact_token="$(csrf <"$tmp/contact-form")"
submit visitor /handlers/contact-submit.php 303 -d 'name=Test&email=fixture%40example.test&message=Test'
grep -Fqi 'Location: /contact?error=1' "$tmp/headers"
submit visitor /handlers/contact-submit.php 303 -d "_csrf=$contact_token&website=bot&name=Test&email=fixture%40example.test&message=Test"
grep -Fqi 'Location: /contact?error=1' "$tmp/headers"
submit admin /admin/login 302 -d "_csrf=$token&email=operator@example.test&password=smoke-only-password"
token="$(get admin /admin/trainings/new | csrf)"
submit admin /admin/trainings/new 303 -F "_csrf=$token" -F 'title=Advanced Learning' -F 'status=draft' -F 'description=**Practical overview**' -F 'header_image=@assets/images/haleef-logo.webp'
[[ "$(status /training/courses/advanced-learning)" == 404 ]]
[[ "$(status /admin/preview/trainings/1)" == 302 ]]
get admin /admin/preview/trainings/1 >"$tmp/preview"
grep -q '<strong>Practical overview</strong>' "$tmp/preview"
get admin /admin/trainings/1/edit >/dev/null
submit admin /admin/trainings/1/edit 303 -F "_csrf=$token" -F 'title=Advanced Learning Updated' -F 'slug=advanced-learning' -F 'status=published' -F 'description=Practical overview' -F 'audience=Clinical researchers' -F 'learning_outcomes=Applied methods'
submit admin /admin/trainers/new 303 -F "_csrf=$token" -F 'name=Test Trainer' -F 'is_published=1' -F 'linkedin_url=https://www.linkedin.com/in/test-trainer/?trk=fixture' -F 'company_showcase_enabled=1' -F 'company_name=Example Studio' -F 'company_about=Training and development.' -F 'company_logo=@assets/images/haleef-logo.webp'
submit admin /admin/trainers/new 303 -F "_csrf=$token" -F 'name=Second Instructor' -F 'is_published=1'
submit admin /admin/trainers/1/edit 200 -F "_csrf=$token" -F 'name=Test Trainer' -F 'slug=test-trainer' -F 'is_published=1' -F 'linkedin_url=https://linkedin.com.example.test/in/invalid'
grep -q 'Enter an HTTPS LinkedIn profile URL' "$tmp/body"
submit admin /admin/trainers/1/edit 303 -F "_csrf=$token" -F 'name=Test Trainer' -F 'slug=test-trainer' -F 'headline=Lead instructor' -F 'is_published=1'
assignment() { grep -o 'name="trainer_assignment_version" value="[a-f0-9]*"' | cut -d '"' -f4; }
empty_assignment="$(get admin /admin/editions/new | assignment)"
edition=(-F "_csrf=$token" -F 'slug=advanced-learning-2035' -F 'training_id=1' -F 'starts_at=2035-02-23T10:00'
    -F 'ends_at=2035-02-25T14:00' -F 'timezone=Europe/Paris' -F 'delivery_mode=online' -F 'registration_status=open'
    -F 'currency=EUR' -F 'price=1199' -F 'capacity=2' -F 'features[]=interactive'
    -F 'zoom_url=https://zoom.us/j/123456789')
printf '%%PDF-1.4\n%%%%EOF\n' >"$tmp/agenda.pdf"
submit admin /admin/editions/new 303 "${edition[@]}" -F 'publication_status=draft' -F "agenda=@$tmp/agenda.pdf" -F 'trainer_ids[]=1' -F "trainer_assignment_version=$empty_assignment"
one_assignment="$(get admin /admin/editions/1/edit | assignment)"
submit admin /admin/editions/1/edit 303 "${edition[@]}" -F 'publication_status=draft' -F 'trainer_ids[]=1' -F 'trainer_ids[]=2' -F "trainer_assignment_version=$one_assignment"
submit admin /admin/editions/1/edit 200 "${edition[@]}" -F 'publication_status=draft' -F 'trainer_ids[]=1' -F "trainer_assignment_version=$one_assignment"
grep -q 'Trainer assignments changed' "$tmp/body"
path=/training/courses/advanced-learning/advanced-learning-2035
[[ "$(status "$path")" == 404 ]]
[[ "$(status /training/register/advanced-learning-2035)" == 404 ]]
get admin /admin/preview/editions/1 >"$tmp/preview"
grep -q 'Download form disabled in preview' "$tmp/preview"
grep -q 'Test Trainer' "$tmp/preview"
grep -q 'Second Instructor' "$tmp/preview"
grep -q 'haleef-learning\|resources.haleeflearning.com' "$tmp/preview"
! grep -q 'zoom.us/j/' "$tmp/preview" || exit 1
! grep -q 'data-learning-share' "$tmp/preview" || exit 1
grep -q 'How do I book and confirm my place?' "$tmp/preview"
[[ "$(status /admin/faqs)" == 302 ]]
get admin /admin/faqs >"$tmp/library"
grep -q 'Reusable FAQs' "$tmp/library"
submit admin /admin/faqs 303 -d "_csrf=$token&id=0&action=library&question=Library+question&answer=Library+answer&enabled=1"
get admin /admin/editions/1/faqs >"$tmp/faq"
grep -q 'Library question' "$tmp/faq"
submit admin /admin/editions/1/faqs 303 -d "_csrf=$token&id=1&action=shared&enabled=1&question=Edition+date+question&answer=**Edition+answer**"
submit admin /admin/editions/1/faqs 303 -d "_csrf=$token&id=2&action=shared"
submit admin /admin/editions/1/faqs 303 -d "_csrf=$token&id=0&action=custom&enabled=1&question=Local+question&answer=Local+answer"
submit admin /admin/editions/1/faqs 303 -d "_csrf=$token&id=3&action=shared"
submit admin /admin/editions/1/faqs 303 -d "_csrf=$token&id=3&action=inherit"
submit admin /admin/editions/1/edit 303 "${edition[@]}" -F 'publication_status=published'
get visitor "$path" >"$tmp/page"
grep -Fq '<span>CET</span>' "$tmp/page"
grep -q 'Start delegate registration' "$tmp/page"
grep -q 'Seats are limited' "$tmp/page"
grep -Fq '€1,199.00' "$tmp/page"
grep -q 'Meet your trainers' "$tmp/page"
grep -q 'Second Instructor' "$tmp/page"
grep -q 'href="https://www.linkedin.com/in/test-trainer/"' "$tmp/page"
grep -q 'class="learn-share-whatsapp"' "$tmp/page"
grep -q '<section id="overview"' "$tmp/page"
grep -q '<section id="audience"' "$tmp/page"
grep -q '<section id="outcomes"' "$tmp/page"
grep -q 'Edition date question' "$tmp/page"
grep -q '<strong>Edition answer</strong>' "$tmp/page"
grep -q 'Local question' "$tmp/page"
grep -q 'Library question' "$tmp/page"
grep -q 'Where do I find the joining instructions?' "$tmp/page"
! grep -q 'How do I book and confirm my place?' "$tmp/page" || exit 1
grep -q "rel=\"canonical\" href=\"https://www.haleefglobal.com$path\"" "$tmp/page"
grep -q 'data-learning-share' "$tmp/page"
grep -q 'Interactive sessions' "$tmp/page"
grep -q 'Email me the agenda' "$tmp/page"
grep -q 'learning-reveal.js' "$tmp/page"
! grep -q 'zoom.us/j/\|/agendas/' "$tmp/page" || exit 1
visitor="$(csrf <"$tmp/page")"
[[ "$(status /training/agenda-request)" == 405 ]]
[[ "$(status /admin/agenda-email)" == 302 ]]
get admin /admin/agenda-email >"$tmp/template"
grep -q 'Agenda request template' "$tmp/template"
submit admin /admin/agenda-email 303 --data-urlencode "_csrf=$token" --data-urlencode 'subject=Agenda for {{training_title}}' --data-urlencode 'body=Hello, here is the agenda for {{edition_date}}: {{agenda_url}}'
get visitor /training >"$tmp/training"
grep -q 'Request the agenda' "$tmp/training"
grep -Fq 'href="#agenda">Request the agenda' "$tmp/training"
grep -Fq 'id="agenda"' "$tmp/training"
get visitor /training/courses >"$tmp/calendar"
grep -Fq '<span>CET</span>' "$tmp/calendar"
grep -q 'Request the agenda' "$tmp/calendar"
grep -Fq '€1,199.00' "$tmp/calendar"
for i in 1 2; do
    submit visitor /training/agenda-request 303 -d "_csrf=$visitor&email=lead@example.test&edition_id=1&return_path=$path"
    grep -qi "^Location: $path#agenda" "$tmp/headers"
done
get visitor "$path" >"$tmp/requested"
grep -q 'Check your inbox' "$tmp/requested"
php bin/smoke-agenda-worker.php
get admin /admin/agenda-email >"$tmp/jobs"
grep -q 'lead@example.test' "$tmp/jobs"
grep -q '>sent<' "$tmp/jobs"
get admin /admin/agenda-interest >"$tmp/leads"
[[ "$(grep -o 'lead@example.test' "$tmp/leads" | wc -l)" == 1 ]]
grep -Fq '/admin/agenda-interest?format=csv' "$tmp/leads"
[[ "$(status '/admin/agenda-interest?format=csv')" == 302 ]]
curl -fsS -b "$tmp/admin" -D "$tmp/lead-headers" -o "$tmp/lead-csv" "$base/admin/agenda-interest?format=csv"
grep -qi '^Content-Disposition: attachment; filename="agenda-interest-' "$tmp/lead-headers"
grep -qi '^Cache-Control: private, no-store' "$tmp/lead-headers"
grep -Fq 'Email,Programme,Edition,' "$tmp/lead-csv"
grep -Fq 'lead@example.test,' "$tmp/lead-csv"
get admin /admin/resources >"$tmp/resources"
grep -q 'resources.haleeflearning.com/agendas/' "$tmp/resources"
php -r 'require "includes/bootstrap.php"; $pdo=Haleef\Support\Database::connection(); $q=$pdo->prepare("INSERT INTO media_assets (brand_id,title,asset_type,storage_disk,storage_path,original_name,mime_type,size_bytes,is_published) SELECT id,?,?,?,?,?,?,1,1 FROM brands WHERE code=?"); for($i=1;$i<=51;$i++) $q->execute(["Pagination pack $i","course_material","public_local","fixture-$i.png","fixture-$i.png","image/png","haleef-learning"]); $q->execute(["Pagination pack 100%","brochure","public_local","fixture.pdf","fixture.pdf","application/pdf","haleef-learning"]);'
filter='/admin/resources?q=ination%20pack&category=course_material&type=image%2Fpng'
get admin "$filter" >"$tmp/resources-first"
[[ "$(grep -o 'data-resource-row' "$tmp/resources-first" | wc -l)" -eq 50 ]]
grep -Fq 'Showing 1–50 of 51 files' "$tmp/resources-first"
grep -Fq 'type=image%2Fpng&amp;page=2#resources-list' "$tmp/resources-first"
get admin "$filter&page=2" >"$tmp/resources-second"
[[ "$(grep -o 'data-resource-row' "$tmp/resources-second" | wc -l)" -eq 1 ]]
grep -Fq 'Showing 51–51 of 51 files' "$tmp/resources-second"
grep -Fq '</svg>PNG' "$tmp/resources-second"
get admin '/admin/resources?q=%25&type=application%2Fpdf' >"$tmp/resources-literal"
[[ "$(grep -o 'data-resource-row' "$tmp/resources-literal" | wc -l)" -eq 1 ]]
grep -Fq '</svg>PDF' "$tmp/resources-literal"
get admin '/admin/resources?q=%25&category=course_material' >"$tmp/resources-empty"
grep -Fq 'No files match these filters.' "$tmp/resources-empty"
get visitor /training/trainers/test-trainer >"$tmp/trainer"
grep -Fq 'class="site-header site-header-solid"' "$tmp/trainer"
grep -q "$path" "$tmp/trainer"
grep -q 'Share this speaker' "$tmp/trainer"
grep -q 'www.linkedin.com/sharing/share-offsite' "$tmp/trainer"
grep -q 'class="learning-trainer-social" href="https://www.linkedin.com/in/test-trainer/"' "$tmp/trainer"
grep -Fq 'id="trainer-company-title">Example Studio' "$tmp/trainer"
grep -Fq 'Training and development.' "$tmp/trainer"
grep -Eq 'class="learning-company-logo".*trainer-profiles/' "$tmp/trainer"
get visitor /training/trainers/second-instructor >"$tmp/no-company"
if grep -Fq 'id="trainer-company-title"' "$tmp/no-company"; then exit 1; fi
submit admin /admin/payment-methods 302 -d "_csrf=$token&enabled=1&account_name=Haleef&bank_name=TestBank&iban=TEST"
get visitor /training/register/advanced-learning-2035 >"$tmp/register"
grep -Fq '<span>CET</span>' "$tmp/register"
grep -Fq '€1,199.00' "$tmp/register"
grep -q 'Delegate Registration' "$tmp/register"
grep -q '<h1>Advanced Learning Updated</h1>' "$tmp/register"
grep -q 'Add another delegate' "$tmp/register"
register=/training/register/advanced-learning-2035
delegate_args() {
    local i=$1 first=$2 email=$3
    args+=(-F "delegates[$i][first_name]=$first" -F "delegates[$i][surname]=Example" -F "delegates[$i][job_title]=Director"
        -F "delegates[$i][phone]=+971500000000" -F "delegates[$i][email]=$email" -F "delegates[$i][company]=TestCo")
}
common=(-F "_csrf=$visitor" -F 'payment_method=bank_transfer')
args=()
delegate_args 0 Taylor attendee@example.test
submit visitor "$register" 200 "${common[@]}" "${args[@]}" -F 'add_delegate=1'
grep -Fq '€2,398.00' "$tmp/body"
grep -Fq 'value="Taylor"' "$tmp/body"
delegate_args 1 Casey ATTENDEE@example.test
submit visitor "$register" 200 "${common[@]}" "${args[@]}"
grep -q 'Each delegate must have a different email address' "$tmp/body"
args=()
delegate_args 0 Taylor attendee@example.test
delegate_args 1 Casey casey@example.test
group=("${args[@]}")
delegate_args 2 Harper harper@example.test
submit visitor "$register" 200 "${common[@]}" "${args[@]}"
grep -q 'There are not enough seats for your group' "$tmp/body"
submit visitor "$register" 302 "${common[@]}" "${group[@]}"
booking="$(awk 'tolower($1)=="location:" {gsub(/\r/,"",$2);print $2}' "$tmp/headers")"
[[ "$booking" == /training/booking/* ]]
get visitor "$booking" >"$tmp/booking"
grep -Fq '2 delegates · €2,398.00 total' "$tmp/booking"
! grep -q 'zoom.us/j/' "$tmp/booking" || exit 1
submit admin /admin/orders/1 302 -F "_csrf=$token" -F 'reference=SMOKE' -F 'amount=2398' -F "evidence=@$tmp/agenda.pdf"
get visitor "$booking" >"$tmp/booking"
grep -q 'Join on Zoom' "$tmp/booking"
get admin /admin/orders/1 >"$tmp/order"
grep -q 'Taylor Example' "$tmp/order"
grep -q 'Casey Example' "$tmp/order"
[[ "$(grep -o 'status-confirmed' "$tmp/order" | wc -l)" == 2 ]]
printf 'Learning smoke checks passed.\n'
