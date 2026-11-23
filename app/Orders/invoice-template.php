<?php
$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$multiline = static fn(string $value): string => nl2br($esc($value));
$money = static fn(int $cents): string => number_format($cents / 100, 2, '.', ',');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Invoice <?= $esc($invoice['number']) ?></title>
<style>
@page { margin: 35pt 38pt 48pt; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #211b2d; line-height: 1.48; }
table { border-collapse: collapse; width: 100%; }
.letterhead { border-bottom: 1.2pt solid #473161; padding-bottom: 14pt; }
.issuer { text-align: right; width: 68%; }
.issuer strong { font-size: 15pt; color: #473161; }
h1 { font-size: 30pt; color: #473161; margin: 19pt 0 9pt; }
h2 { font-size: 12pt; color: #473161; margin: 0 0 5pt; }
.status { color: #473161; background: #f0eaf5; padding: 3pt 7pt; font-size: 9pt; }
.facts { margin: 14pt 0 18pt; }
.facts td { width: 50%; vertical-align: top; padding-right: 14pt; overflow-wrap: break-word; }
.items { table-layout: fixed; }
.items th { background: #473161; color: white; text-align: left; }
.items th, .items td { padding: 8pt; overflow-wrap: break-word; }
.items td { border-bottom: 1pt solid #ded6e6; }
.items .numeric { text-align: right; }
.total { margin: 10pt 0 18pt; text-align: right; color: #473161; font-size: 13pt; }
.transfer { border-top: 1pt solid #ded6e6; padding-top: 12pt; }
.transfer-row { margin: 2pt 0; overflow-wrap: break-word; }
.note { margin: 13pt 0 8pt; }
.footer { border-top: 1pt solid #ded6e6; margin-top: 20pt; padding-top: 7pt; font-size: 9pt; color: #564c62; }
</style></head>
<body>
<table class="letterhead"><tr><td><img src="data:image/png;base64,<?= $logo ?>" width="73" alt="Haleef"></td>
<td class="issuer"><strong><?= $esc($invoice['issuer']['name']) ?></strong><?php if ($invoice['issuer']['license'] !== ''): ?><br>Licence no. <?= $esc($invoice['issuer']['license']) ?><?php endif; ?></td></tr></table>
<h1>Invoice</h1><span class="status">Payment pending at time of issue</span>
<table class="facts"><tr><td>
<h2>Invoice to</h2>
<?php if ($invoice['company'] !== ''): ?><strong><?= $esc($invoice['company']) ?></strong><br><?php endif; ?>
<?= $esc($invoice['name']) ?><br>
<?php if ($invoice['address'] !== ''): ?><?= $multiline($invoice['address']) ?><br><?php endif; ?>
<?= $esc($invoice['email']) ?><br><?= $esc($invoice['phone']) ?>
</td><td>
<h2>Invoice details</h2>
<strong>Invoice:</strong> <?= $esc($invoice['number']) ?><br>
<strong>Booking:</strong> <?= $esc($invoice['order']) ?><br>
<strong>Issued:</strong> <?= $esc($invoice['issued']) ?><br>
<strong>Payment due:</strong> <?= $esc($invoice['due']) ?>
</td></tr></table>
<table class="items"><thead><tr><th style="width:48%">Description</th><th class="numeric" style="width:9%">Qty</th><th class="numeric" style="width:20%">Unit price</th><th class="numeric" style="width:23%">Amount</th></tr></thead>
<tbody><tr><td><strong><?= $esc($invoice['training']) ?></strong><br><?= $esc($invoice['edition']) ?></td><td class="numeric"><?= $invoice['quantity'] ?></td>
<td class="numeric"><?= $esc($invoice['currency']) ?> <?= $money($invoice['unit_cents']) ?></td><td class="numeric"><?= $esc($invoice['currency']) ?> <?= $money($invoice['total_cents']) ?></td></tr></tbody></table>
<p class="total"><strong>Total due: <?= $esc($invoice['currency']) ?> <?= $money($invoice['total_cents']) ?></strong></p>
<div class="transfer"><h2>Bank transfer details</h2>
<?php foreach (['account_name'=>'Account name','bank_name'=>'Bank','iban'=>'IBAN','swift'=>'SWIFT / BIC','reference'=>'Payment reference'] as $key => $label): ?>
<?php if ($invoice['bank'][$key] !== ''): ?><div class="transfer-row"><strong><?= $label ?>:</strong> <?= $esc($invoice['bank'][$key]) ?></div><?php endif; ?>
<?php endforeach; ?>
<?php if ($invoice['bank']['instructions'] !== ''): ?><p class="note"><?= $multiline($invoice['bank']['instructions']) ?></p><?php endif; ?>
</div>
<div class="footer"><?= $multiline($invoice['issuer']['address']) ?><br>Invoice enquiries: <?= $esc($invoice['issuer']['email']) ?></div>
</body></html>
