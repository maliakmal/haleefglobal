<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const SITE_NAME = 'Haleef';
const SITE_EMAIL = 'team@haleefglobal.com';
const LINKEDIN_URL = 'https://www.linkedin.com/company/haleefglobal/';
const MONEYAURA_URL = 'https://moneyauraglobal.com/';

$services = [
    [
        'number' => '01',
        'title' => 'Event Management',
        'slug' => 'event-management',
        'summary' => 'Complete delivery for conferences, summits, awards, executive gatherings and corporate experiences.',
        'intro' => 'From the first working brief to the final guest departure, Haleef coordinates the people, partners and production behind purposeful business events.',
        'image' => '/assets/images/service-events.webp',
        'image_credit' => 'Evangeline Shaw',
        'image_source' => 'https://unsplash.com/photos/nwLTVwb7DbU',
        'items' => ['Conferences & summits', 'Awards & gala ceremonies', 'Executive roundtables', 'Speaker & delegate management', 'Venue & supplier coordination', 'On-site operations'],
        'ideal_for' => 'Organisations that need one accountable delivery partner to turn a confirmed objective—or an early event idea—into a professionally executed experience.',
        'promise' => 'One connected delivery plan from concept to closing moment.',
        'stages' => [
            ['Discover', 'Define the audience, purpose, format, measures of success and practical constraints.'],
            ['Design', 'Shape the program, experience, production plan, supplier scope and delivery timeline.'],
            ['Deliver', 'Coordinate speakers, partners, venue, production and on-site operations as one team.'],
        ],
        'outcomes' => ['A clear event roadmap and ownership structure', 'A coordinated experience for speakers, partners and delegates', 'Reduced delivery risk across suppliers and workstreams', 'A post-event view of results and future opportunities'],
    ],
    [
        'number' => '02',
        'title' => 'Event Advisory & Support',
        'slug' => 'event-advisory',
        'summary' => 'Experienced strategic guidance and practical delivery support for internal event teams.',
        'intro' => 'Bring us in to shape the whole event or strengthen the areas where your team needs experience, capacity or a clearer plan.',
        'image' => '/assets/images/service-workshop.webp',
        'image_credit' => 'Headway',
        'image_source' => 'https://unsplash.com/photos/5QgIuuBxKwM',
        'items' => ['Concept & format design', 'Feasibility & budgeting', 'Agenda & content strategy', 'Sponsorship propositions', 'Delivery health checks', 'Event rescue support'],
        'ideal_for' => 'Internal event and marketing teams that already own the event but need senior thinking, additional capacity or experienced intervention in a specific area.',
        'promise' => 'The right level of support without taking control away from your team.',
        'stages' => [
            ['Assess', 'Review the objective, plan, budget, responsibilities, dependencies and current delivery health.'],
            ['Strengthen', 'Resolve gaps, clarify decisions and provide hands-on leadership where pressure is highest.'],
            ['Transfer', 'Leave the team with practical decisions, documentation and a stronger path to delivery.'],
        ],
        'outcomes' => ['Sharper event positioning and format', 'Earlier visibility of budget and delivery risks', 'Clearer ownership across internal and external teams', 'Specialist support that fits the existing operation'],
    ],
    [
        'number' => '03',
        'title' => 'Learning Experiences',
        'slug' => 'learning-experiences',
        'summary' => 'Workshops, masterclasses and professional learning experiences designed around useful outcomes.',
        'intro' => 'We turn expertise into engaging learning environments for companies, institutions, associations and professional communities.',
        'image' => '/assets/images/service-workshop.webp',
        'image_credit' => 'Headway',
        'image_source' => 'https://unsplash.com/photos/5QgIuuBxKwM',
        'items' => ['Corporate training events', 'Workshops & masterclasses', 'Executive learning', 'Knowledge forums', 'Professional development', 'Hybrid learning formats'],
        'ideal_for' => 'Companies, associations, training providers and educational institutions that want participants to leave with useful knowledge—not merely attendance certificates.',
        'promise' => 'Learning environments designed around participation, application and retention.',
        'stages' => [
            ['Frame', 'Define the participants, learning objectives and the change the experience should create.'],
            ['Curate', 'Develop the format, facilitator mix, content flow, materials and interaction model.'],
            ['Activate', 'Deliver an engaging experience and capture the insight needed to improve future sessions.'],
        ],
        'outcomes' => ['A program connected to defined learning objectives', 'Relevant facilitators and participant-centered formats', 'Smooth delivery across in-person or hybrid environments', 'Feedback and insight for continued program development'],
    ],
    [
        'number' => '04',
        'title' => 'Technology Advisory',
        'slug' => 'technology-advisory',
        'summary' => 'Practical technology guidance for events, growing businesses and digital initiatives.',
        'intro' => 'We help organisations select, connect and improve the technology that supports their events and business operations.',
        'image' => '/assets/images/service-technology.webp',
        'image_credit' => 'Stephen Dawson',
        'image_source' => 'https://unsplash.com/photos/qwtCeJ5cLYs',
        'items' => ['Event websites & registration', 'CRM & lead flows', 'Workflow automation', 'Data & reporting', 'AI opportunity assessment', 'Technical project oversight'],
        'ideal_for' => 'Event businesses and growing organisations that need practical technology decisions, better-connected workflows or oversight of a digital initiative.',
        'promise' => 'Technology selected around the operation—not technology for its own sake.',
        'stages' => [
            ['Map', 'Understand the current workflow, systems, data, users and business constraints.'],
            ['Decide', 'Identify the right platform, integration or improvement path with clear trade-offs.'],
            ['Enable', 'Guide delivery, vendors and implementation while keeping the business objective visible.'],
        ],
        'outcomes' => ['A clearer and more efficient attendee journey', 'Less manual work between forms, teams and systems', 'More useful event and commercial reporting', 'Better-informed technology investment decisions'],
    ],
];

function page_url(string $path = ''): string
{
    return $path === '' ? '/' : '/' . trim($path, '/');
}

function service_url(string $slug): string
{
    return '/services/' . rawurlencode($slug);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function service_by_slug(string $slug): ?array
{
    global $services;
    foreach ($services as $service) {
        if ($service['slug'] === $slug) return $service;
    }
    return null;
}
