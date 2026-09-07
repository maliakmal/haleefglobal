<?php
declare(strict_types=1);

const SITE_NAME = 'Haleef';
const SITE_EMAIL = 'hello@thehaleef.com';
const LINKEDIN_URL = 'https://www.linkedin.com/company/haleefglobal/';
const MONEYAURA_URL = 'https://moneyauraglobal.com/';

$services = [
    [
        'number' => '01',
        'title' => 'Event Management',
        'slug' => 'event-management',
        'summary' => 'Complete delivery for conferences, summits, awards, executive gatherings and corporate experiences.',
        'intro' => 'From the first working brief to the final guest departure, Haleef coordinates the people, partners and production behind purposeful business events.',
        'items' => ['Conferences & summits', 'Awards & gala ceremonies', 'Executive roundtables', 'Speaker & delegate management', 'Venue & supplier coordination', 'On-site operations'],
    ],
    [
        'number' => '02',
        'title' => 'Event Advisory & Support',
        'slug' => 'event-advisory',
        'summary' => 'Experienced strategic guidance and practical delivery support for internal event teams.',
        'intro' => 'Bring us in to shape the whole event or strengthen the areas where your team needs experience, capacity or a clearer plan.',
        'items' => ['Concept & format design', 'Feasibility & budgeting', 'Agenda & content strategy', 'Sponsorship propositions', 'Delivery health checks', 'Event rescue support'],
    ],
    [
        'number' => '03',
        'title' => 'Learning Experiences',
        'slug' => 'learning-experiences',
        'summary' => 'Workshops, masterclasses and professional learning experiences designed around useful outcomes.',
        'intro' => 'We turn expertise into engaging learning environments for companies, institutions, associations and professional communities.',
        'items' => ['Corporate training events', 'Workshops & masterclasses', 'Executive learning', 'Knowledge forums', 'Professional development', 'Hybrid learning formats'],
    ],
    [
        'number' => '04',
        'title' => 'Technology Advisory',
        'slug' => 'technology-advisory',
        'summary' => 'Practical technology guidance for events, growing businesses and digital initiatives.',
        'intro' => 'We help organisations select, connect and improve the technology that supports their events and business operations.',
        'items' => ['Event websites & registration', 'CRM & lead flows', 'Workflow automation', 'Data & reporting', 'AI opportunity assessment', 'Technical project oversight'],
    ],
];

function page_url(string $path = ''): string
{
    return $path === '' ? 'index.php' : $path . '.php';
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
