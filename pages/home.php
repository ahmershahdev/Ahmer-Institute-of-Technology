<?php
require_once __DIR__ . '/../backend/site.php';
$programs = ait_catalog_programs();
$home_eyebrow = ait_site_data('home_eyebrow', 'Ahmer Institute for Technology');
$home_headline = ait_site_data('home_headline', 'Build a future that feels');
$home_intro = ait_site_data('home_intro', 'A forward-looking university for people who want to think clearly, make boldly, and leave a mark that matters.');
ait_public_header('Home', 'home');
?>
<main>
    <section class="hero">
        <div class="hero-copy">
            <p class="eyebrow"><?= htmlspecialchars($home_eyebrow); ?></p>
            <h1><?= htmlspecialchars($home_headline); ?> <em data-typing='["possible.","useful.","entirely yours."]'>possible.</em></h1>
            <p class="lede"><?= htmlspecialchars($home_intro); ?></p>
            <div class="hero-actions"><a class="button button-coral" href="login">Admissions open <span>↗</span></a><a class="text-link" href="programs">Explore programs <span>↗</span></a></div>
        </div>
        <div class="hero-art">
            <div class="hero-art-mark"><i class="bi bi-mortarboard"></i></div>
            <div class="art-label"><strong>Fall 2026 intake</strong><br>Applications are now being reviewed.</div>
        </div>
    </section>
    <div class="ticker"><span>Curiosity is a practice</span><span>Learn by making</span><span>Ideas need somewhere to go</span><span>Admissions open</span></div>
    <section class="section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">AIT in a glance</p>
                <h2>A small campus with a wide horizon.</h2>
            </div>
            <p>We pair the focus of a close academic community with the ambition and tools of a technology institute.</p>
        </div>
        <div class="kpi-grid">
            <div class="kpi"><strong>4.8k</strong><span>students learning across campus</span></div>
            <div class="kpi"><strong>126</strong><span>faculty and visiting mentors</span></div>
            <div class="kpi"><strong>15</strong><span>undergraduate programs</span></div>
            <div class="kpi"><strong>92%</strong><span>graduates in work or further study</span></div>
        </div>
    </section>
    <section class="split-band">
        <div>
            <p class="eyebrow">Momentum, measured</p>
            <h2>Every year should open more doors.</h2>
            <p>From first prototype to final capstone, AIT is designed around visible progress. Students leave with a degree, a network, and evidence of what they can do.</p><a class="button button-coral" href="about">Why AIT <span>↗</span></a>
        </div>
        <div class="chart">
            <div class="chart-canvas"><canvas id="admissionsChart" aria-label="Student participation growth chart"></canvas></div>
            <div class="chart-label"><span>Admissions momentum</span><span>2022 — 2026</span></div>
        </div>
    </section>
    <section class="section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Find your direction</p>
                <h2>Programs for the work ahead.</h2>
            </div><a class="text-link" href="programs">View all 15 programs ↗</a>
        </div>
        <div class="home-cards"><?php foreach (array_slice($programs, 0, 3) as $program): ?><article class="feature-card reveal"><span class="card-number"><?= htmlspecialchars($program['code']); ?></span>
                    <h3><?= htmlspecialchars($program['name']); ?></h3>
                    <p><?= htmlspecialchars($program['faculty']); ?> · <?= htmlspecialchars((string) ($program['duration_years'] ?: '4')); ?> years</p>
                </article><?php endforeach; ?></div>
    </section>
    <section class="section pathways-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Start somewhere meaningful</p>
                <h2>Choose the path that moves you forward.</h2>
            </div>
            <p>Whether you are choosing a degree, exploring research, or planning your next campus visit, AIT keeps the next step visible.</p>
        </div>
        <div class="pathway-grid"><a class="pathway-card pathway-card-featured" href="programs"><span class="card-number">01 / ACADEMICS</span>
                <h3>Undergraduate programs</h3>
                <p>Build a foundation in computing, engineering, science, humanities, and business.</p><strong>Explore the catalogue ↗</strong>
            </a><a class="pathway-card" href="departments"><span class="card-number">02 / DISCOVER</span>
                <h3>Departments & schools</h3>
                <p>Meet the academic communities where disciplines cross-pollinate.</p><strong>Meet our departments ↗</strong>
            </a><a class="pathway-card" href="faculty"><span class="card-number">03 / PEOPLE</span>
                <h3>Faculty & mentors</h3>
                <p>Learn alongside people who bring practice, research, and generosity to the room.</p><strong>Find your mentors ↗</strong>
            </a><a class="pathway-card" href="admissions"><span class="card-number">04 / BEGIN</span>
                <h3>Admissions open</h3>
                <p>Turn your interest into a clear application with milestones you can track.</p><strong>See how to apply ↗</strong>
            </a></div>
    </section>
    <section class="editorial-band">
        <div class="editorial-image"><i class="bi bi-people"></i></div>
        <div class="editorial-copy">
            <p class="eyebrow">A culture of making</p>
            <h2>Good ideas become useful when people build together.</h2>
            <p>AIT is a place for studio critiques, late lab sessions, honest questions, and the satisfying moment when a rough idea starts working. Our students learn to connect technical skill with context, care, and consequence.</p><a class="text-link" href="campus-life">See life at AIT ↗</a>
        </div>
    </section>
    <section class="section news-section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">From the institute</p>
                <h2>What's moving at AIT.</h2>
            </div><a class="text-link" href="contact">Connect with us ↗</a>
        </div>
        <div class="news-grid">
            <article class="news-card"><span class="news-date">18 SEP 2026 · RESEARCH</span>
                <h3>AIT launches an applied AI studio for local challenges.</h3>
                <p>Students and faculty will work with community partners on small, testable ideas with a visible public benefit.</p><a href="departments">Read the story ↗</a>
            </article>
            <article class="news-card"><span class="news-date">04 SEP 2026 · CAMPUS</span>
                <h3>New student societies open their doors for the fall.</h3>
                <p>Robotics, debate, design, entrepreneurship, and community action are looking for their next founding members.</p><a href="campus-life">Find your people ↗</a>
            </article>
            <article class="news-card"><span class="news-date">28 AUG 2026 · ADMISSIONS</span>
                <h3>Fall 2026 applications are now being reviewed.</h3>
                <p>Keep your documents ready, follow your portal timeline, and contact our admissions team when you need a hand.</p><a href="admissions">View admissions ↗</a>
            </article>
        </div>
    </section>
    <section class="section" style="padding-top:0">
        <div class="feature-card" style="background:var(--yellow);border:0;display:flex;justify-content:space-between;align-items:center;gap:30px;min-height:150px">
            <div>
                <p class="eyebrow" style="color:var(--teal-dark)">Ready when you are</p>
                <h2 style="font:600 32px 'Space Grotesk',sans-serif;margin:0">Your next chapter starts with one account.</h2>
            </div><a class="button" href="login">Student login <span>↗</span></a>
        </div>
    </section>
</main>
<?php ait_public_footer(); ?>