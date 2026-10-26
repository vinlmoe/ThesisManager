// Captures du guide sur le Moodle de test chargé par seed.php.
//
// Usage : node docs/guides/captures/shots.js [capture ...]
//   sans argument : toutes les captures, dans l'ordre (certaines changent l'état d'un dossier :
//   relancer reset.sh avant de tout refaire).
//   MOODLE_URL (défaut http://localhost:8080).
//
// Cadrage : la fenêtre est agrandie à toute la hauteur de la page, puis chaque capture est
// découpée sur des éléments de la page (fin du contenu, d'un formulaire, d'une section, d'un
// bloc), jamais à une hauteur fixe.

const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = process.env.MOODLE_URL || 'http://localhost:8080';
const HERE = __dirname;
const OUT = path.join(HERE, '..', 'images');
const TMP = fs.mkdtempSync('/tmp/captures-');
const ids = JSON.parse(fs.readFileSync(path.join(HERE, 'ids.json'), 'utf8'));
const CM = ids.cmid;
const WIDTH = 1220;

const url = (p) => BASE + p;
const these = (script, params = {}) => url(`/mod/these/${script}?` + new URLSearchParams({ id: CM, ...params }));
const synthesis = (script, params = {}) => url(`/mod/thesesynthesis/${script}?` + new URLSearchParams({ id: ids.synthesiscmid, ...params }));
const ts = (date) => String(Math.floor(new Date(date).getTime() / 1000));

let browser;

// Contexte connecté (connexion vérifiée : la première tentative échoue parfois).
async function as(username) {
  const ctx = await browser.newContext({ viewport: { width: WIDTH, height: 900 }, locale: 'fr-FR' });
  const page = await ctx.newPage();
  if (!username) {
    return page;
  }
  for (let attempt = 0; attempt < 4; attempt++) {
    await page.goto(url('/login/index.php'));
    if (!page.url().includes('/login/')) {
      return page;
    }
    await page.fill('#username', username);
    await page.fill('#password', 'Test1234!');
    await Promise.all([page.waitForNavigation(), page.click('#loginbtn')]);
    await page.goto(url('/my/'));
    if (!page.url().includes('/login/')) {
      return page;
    }
  }
  throw new Error('Connexion impossible : ' + username);
}

// Ouvre une page dans une fenêtre aussi haute que son contenu (plus une marge pour les sections
// qu'on déplie ensuite) : aucun défilement, les coordonnées mesurées sont celles de l'image.
async function open(page, address) {
  await page.setViewportSize({ width: WIDTH, height: 900 });
  await page.goto(address);
  await page.waitForLoadState('networkidle');
  await grow(page);
  return page;
}

async function grow(page) {
  const h = await page.evaluate(() => Math.max(document.documentElement.scrollHeight,
    (document.querySelector('#page') || document.body).scrollHeight));
  await page.setViewportSize({ width: WIDTH, height: Math.max(1200, h + 900) });
  await page.waitForTimeout(400);
}

async function box(page, selector) {
  const b = await page.locator(selector).first().boundingBox();
  if (!b) {
    throw new Error('Élément introuvable : ' + selector);
  }
  return b;
}

async function save(page, name, clip) {
  const file = path.join(OUT, name + '.png');
  await page.screenshot({ path: file, clip: { x: 0, y: Math.max(0, Math.floor(clip.y)), width: WIDTH,
    height: Math.ceil(clip.height) } });
  console.log('capture', name, Math.ceil(clip.height) + 'px');
}

// Du haut de la page jusqu'à la fin d'un élément (par défaut tout le contenu principal).
async function upto(page, name, selector = null, margin = 20) {
  await page.waitForTimeout(300);
  if (!selector) {
    // Contenu principal ; la page d'accès du jury, sans compte, a une mise en page intégrée.
    selector = (await page.locator('#region-main').count()) ? '#region-main' : '[role="main"]';
  }
  const b = await box(page, selector);
  await save(page, name, { y: 0, height: b.y + b.height + margin });
}

// D'un élément à un autre (ou au même), avec une marge.
async function region(page, name, start, end = start, pad = 8) {
  await page.waitForTimeout(300);
  const a = await box(page, start);
  const z = await box(page, end);
  // Marge basse réduite : l'élément suivant commence souvent juste après.
  await save(page, name, { y: a.y - pad, height: z.y + z.height - a.y + pad });
}

// Section du dossier : titre et tableau qui le suit.
async function sectionSel(page, heading) {
  const sel = await page.evaluate((heading) => {
    const h = [...document.querySelectorAll('#region-main h2, #region-main h3, #region-main h4, #region-main h5')].find((e) => e.textContent.trim() === heading);
    if (!h) {
      return null;
    }
    h.setAttribute('data-shot', 'h-' + heading);
    let next = h.nextElementSibling;
    if (next) {
      next.setAttribute('data-shot', 'n-' + heading);
    }
    return true;
  }, heading);
  if (!sel) {
    throw new Error('Section introuvable : ' + heading);
  }
  return [`[data-shot="h-${heading}"]`, `[data-shot="n-${heading}"]`];
}

async function section(page, name, heading) {
  const [h, n] = await sectionSel(page, heading);
  await region(page, name, h, n);
}

async function uptoSection(page, name, heading) {
  const [, n] = await sectionSel(page, heading);
  await upto(page, name, n, 4);
}

const fieldset = (legend) => `#region-main fieldset:has(> legend:text-is("${legend}"))`;
const form = '#region-main form.mform';

// Trace une signature dans le premier pavé de signature de la page.
async function draw(page) {
  const canvas = page.locator(`${form} canvas`).first();
  const b = await canvas.boundingBox();
  await page.mouse.move(b.x + 30, b.y + b.height * 0.6);
  await page.mouse.down();
  for (let i = 0; i <= 60; i++) {
    await page.mouse.move(b.x + 30 + i * ((Math.min(b.width, 420) - 60) / 60),
      b.y + b.height * (0.55 + 0.25 * Math.sin(i / 2.3) * Math.cos(i / 9) - 0.08 * Math.sin(i / 13)));
  }
  await page.mouse.up();
}

async function check(page, selector) {
  const el = page.locator(selector).first();
  if (await el.count() && !(await el.isChecked())) {
    await el.check();
  }
}

async function upload(page, field, file) {
  const fm = page.locator(`#fitem_id_${field}`);
  await fm.locator('.fp-btn-add').first().click();
  await page.locator('.fp-repo-name', { hasText: 'Upload a file' }).first().click();
  const input = page.locator('input[name="repo_upload_file"]').first();
  await input.waitFor({ state: 'attached', timeout: 15000 });
  await input.setInputFiles(file);
  await page.locator('.fp-upload-btn:visible').first().click();
  await fm.locator('.fp-file, .fp-filename').first().waitFor({ timeout: 15000 });
  await page.waitForTimeout(800);
}

// Sections de formulaire : le bouton de pliage est le lien a.fheader de la section.
const toggler = (legend) => `${fieldset(legend)} > div .ftoggler a.fheader`;

async function expand(page, legend, open = true) {
  const collapsed = await page.locator(fieldset(legend)).first().evaluate((f) => f.classList.contains('collapsed'));
  if (collapsed === open) {
    await page.locator(toggler(legend)).first().click();
    await page.waitForTimeout(500);
  }
}

// Replie toutes les sections, puis déplie celles qu'on veut montrer.
async function only(page, legends) {
  const all = await page.evaluate(() => [...document.querySelectorAll('#region-main form.mform fieldset.collapsible')]
    .map((f) => f.querySelector(':scope > legend').textContent.trim()));
  for (const legend of all) {
    await expand(page, legend, legends.includes(legend));
  }
}

async function pdfPage(page, address, name, file) {
  const response = await page.context().request.get(address);
  if (!response.ok()) {
    throw new Error('PDF ' + address + ' : ' + response.status());
  }
  const pdf = path.join(TMP, file);
  fs.writeFileSync(pdf, await response.body());
  const png = path.join(TMP, name);
  execFileSync('pdftoppm', ['-r', '100', '-png', '-singlefile', '-f', '1', '-l', '1', pdf, png]);
  return png + '.png';
}

const sample = (file) => path.join(TMP, file);
function samplePdf(file, title) {
  const out = sample(file);
  execFileSync('convert', ['-size', '1240x1754', 'xc:white', '-font', 'DejaVu-Sans', '-pointsize', '40',
    '-annotate', '+140+220', title, out.replace(/\.pdf$/, '.png')]);
  execFileSync('convert', [out.replace(/\.pdf$/, '.png'), out]);
  return out;
}

const steps = {
  // --- Étudiant -------------------------------------------------------------------------------
  async '01_etudiant_accueil'() {
    const p = await open(await as('emma'), these('view.php'));
    await upto(p, '01_etudiant_accueil');
  },
  async '60_etudiant_sujet_signature'() {
    const p = await open(await as('emma'), these('subject.php'));
    await p.check('#id_studyyear_A5');
    await p.fill('#id_title', "Prise en charge de l'insuffisance rénale chronique chez le chat");
    await p.fill('#id_titleen', 'Management of chronic kidney disease in cats');
    await p.fill('#id_keywords', 'chat ; néphrologie ; insuffisance rénale');
    await p.fill('#id_summary', "Suivi de 80 chats sur deux ans et comparaison de deux protocoles diététiques.");
    await p.selectOption('#id_directorid', { label: 'Hélène Durand' });
    for (const c of ['#id_commitguide', '#id_commitduplicate', '#id_commitethics']) {
      await check(p, c);
    }
    await draw(p);
    await check(p, '#id_signatureconsent');
    await region(p, '60_etudiant_sujet_signature', fieldset("Engagement de l'étudiant"), form);
  },
  async '61_etudiant_sujet_renvoye'() {
    const p = await open(await as('nathan'), these('view.php'));
    await uptoSection(p, '61_etudiant_sujet_renvoye', 'Sujet de thèse');
  },
  async '84_changement_sujet'() {
    const p = await open(await as('lucas'), these('subjectchange.php'));
    await p.fill('#id_changereason', "Le terrain initial n'est plus accessible : enquête recentrée sur les chiens, avec la même directrice.");
    await p.fill('#id_title', 'Antibiorésistance des entérobactéries chez le chien : enquête en clinique');
    for (const c of ['#id_commitguide', '#id_commitduplicate', '#id_commitethics']) {
      await check(p, c);
    }
    await draw(p);
    await check(p, '#id_signatureconsent');
    await upto(p, '84_changement_sujet', form);
  },
  async '52_questionnaire_etudiant'() {
    const p = await open(await as('lucas'), these('questionnaire.php', { dossierid: ids.lucas }));
    const select = p.locator(`${form} select`).first();
    await select.selectOption({ label: 'Chat' });
    const boxes = p.locator(`${form} input[type=checkbox]`);
    await boxes.nth(1).check();
    await boxes.nth(2).check();
    await p.locator(`${form} input[type=text]`).first().fill('Unité de bactériologie, VetAgro Sup');
    await upto(p, '52_questionnaire_etudiant', form);
  },
  async '67_etudiant_soutenance_signature'() {
    const p = await open(await as('lucas'), these('defense.php', { dossierid: ids.lucas }));
    await upload(p, 'manuscript', samplePdf('manuscrit.pdf', 'Manuscrit de thèse'));
    await p.selectOption('#id_proposeddate_day', '19');
    await p.selectOption('#id_proposeddate_month', '11');
    await p.selectOption('#id_proposeddate_year', '2026');
    await p.selectOption('#id_proposeddate_hour', '14');
    await p.selectOption('#id_proposeddate_minute', '00');
    await p.fill('#id_defenselocation', 'Amphithéâtre Bourgelat');
    await only(p, ['Documents de soutenance', 'Proposition de soutenance', 'Signature']);
    await draw(p);
    await check(p, '#id_signatureconsent');
    await upto(p, '67_etudiant_soutenance_signature', form);
  },
  async '91_embargo_depot'() {
    const p = await open(await as('lucas'), these('defense.php', { dossierid: ids.lucas }));
    await expand(p, 'Contrat de diffusion électronique');
    await check(p, '#id_bembargo');
    await p.fill('#id_bembargoreason', 'Publication en cours de soumission dans une revue à comité de lecture.');
    await p.selectOption('#id_bdiffusion', { index: 1 });
    await region(p, '91_embargo_depot', fieldset('Contrat de diffusion électronique'));
  },
  async '92_dossier_diffusion'() {
    const p = await open(await as('sbonnet'), these('dossier.php', { dossierid: ids.chloe }));
    await section(p, '92_dossier_diffusion', 'Diffusion');
  },
  async '75_dossier_corrections'() {
    const p = await open(await as('sbonnet'), these('dossier.php', { dossierid: ids.emilie }));
    await uptoSection(p, '75_dossier_corrections', 'Corrections');
  },
  async '77_etudiant_corrections_renvoyees'() {
    const p = await open(await as('emilie'), these('view.php'));
    await uptoSection(p, '77_etudiant_corrections_renvoyees', 'Corrections');
  },
  async '55_corrections_depot'() {
    const p = await open(await as('emilie'), these('corrections.php', { dossierid: ids.emilie }));
    await upload(p, 'corrections', samplePdf('manuscrit-corrige.pdf', 'Manuscrit corrigé'));
    await p.fill('#id_correctionsdone', "Discussion complétée sur les biais de recrutement (section 4.3, p. 58-60).\n"
      + 'Références bibliographiques reprises au format de la bibliothèque universitaire.');
    await upto(p, '55_corrections_depot', form);
  },
  async '81_etudiant_ajourne'() {
    const p = await open(await as('sarah'), these('view.php'));
    await uptoSection(p, '81_etudiant_ajourne', 'Sujet de thèse');
  },
  async '82_nouveau_depot'() {
    const p = await open(await as('sarah'), these('defense.php', { dossierid: ids.sarah }));
    await upto(p, '82_nouveau_depot', fieldset('Documents de soutenance'));
  },
  async '94_dossier_confidentiel'() {
    const p = await open(await as('scolarite'), these('dossier.php', { dossierid: ids.clara }));
    await section(p, '94_dossier_confidentiel', 'Sujet de thèse');
  },

  // --- Directeur ------------------------------------------------------------------------------
  async '04_directeur_tableau_de_bord'() {
    const p = await open(await as('hdurand'), these('dashboard.php'));
    await uptoSection(p, '04_directeur_tableau_de_bord', 'À traiter');
  },
  async '62_directeur_valide_sujet'() {
    const p = await open(await as('hdurand'), these('review.php', { dossierid: ids.hugo }));
    await check(p, '#id_signatureconsent');
    await upto(p, '62_directeur_valide_sujet', form);
  },
  async '53_rapport_directeur'() {
    const p = await open(await as('hdurand'), these('report.php', { dossierid: ids.chloe }));
    await p.fill('#id_reporttext', "Chloé Lambert a conduit une étude échocardiographique rigoureuse sur 150 chats.\n\n"
      + "La méthode est bien décrite, les résultats sont clairement présentés et la discussion situe le travail par rapport "
      + "à la littérature récente.\n\nJe donne un avis favorable à la soutenance de cette thèse.");
    await check(p, '#id_reportfavorable');
    await upto(p, '53_rapport_directeur', form);
    await Promise.all([p.waitForNavigation(), p.click('#id_submitbutton')]);
  },
  async '70_directeur_valide_soutenance'() {
    const p = await open(await as('hdurand'), these('review.php', { dossierid: ids.chloe }));
    const grid = p.locator(`${form} input[type=checkbox][name^="grid"]`);
    for (let i = 0; i < await grid.count(); i++) {
      await grid.nth(i).check();
    }
    const title = p.locator('#id_directortitle');
    if (await title.count()) {
      await title.fill('Maître de conférences HDR');
    }
    await check(p, '#id_signatureconsent');
    await upto(p, '70_directeur_valide_soutenance', form);
  },
  async '68_directeur_renvoi'() {
    const p = await open(await as('hdurand'), these('review.php', { dossierid: ids.chloe }));
    await p.fill('#id_remark', 'Avancer la date : le président du jury est indisponible ce jour-là.');
    await region(p, '68_directeur_renvoi', fieldset('Commentaire'), form);
  },
  async '69_etudiant_soutenance_renvoyee'() {
    const p = await open(await as('arthur'), these('view.php'));
    await uptoSection(p, '69_etudiant_soutenance_renvoyee', 'Sujet de thèse');
  },
  async '76_directeur_corrections'() {
    const p = await open(await as('hdurand'), these('review.php', { dossierid: ids.alice }));
    await uptoSection(p, '76_directeur_corrections', 'Corrections');
  },

  // --- DEVE -----------------------------------------------------------------------------------
  async '18_tableau_de_bord_deve'() {
    const p = await open(await as('sbonnet'), these('dashboard.php'));
    await uptoSection(p, '18_tableau_de_bord_deve', 'À traiter');
  },
  async '86_planning_semaine'() {
    const p = await open(await as('sbonnet'), these('planning.php', { view: 'week', date: ts('2026-10-28T12:00:00+01:00') }));
    await upto(p, '86_planning_semaine');
  },
  async '87_planning_mois'() {
    const p = await open(await as('sbonnet'), these('planning.php', { view: 'month', date: ts('2026-10-15T12:00:00+02:00') }));
    await upto(p, '87_planning_mois');
  },
  async '88_planning_pdf'() {
    const p = await as('sbonnet');
    const png = await pdfPage(p, these('planning.php', { view: 'month', date: ts('2026-10-15T12:00:00+02:00'), format: 'pdf' }),
      'planning', 'planning.pdf');
    fs.copyFileSync(png, path.join(OUT, '88_planning_pdf.png'));
    console.log('capture 88_planning_pdf');
  },
  async '63_deve_enregistre_sujet'() {
    const p = await open(await as('sbonnet'), these('review.php', { dossierid: ids.ines }));
    await upto(p, '63_deve_enregistre_sujet', form);
  },
  async '71_deve_valide_soutenance'() {
    const p = await open(await as('sbonnet'), these('review.php', { dossierid: ids.zoe }));
    await expand(p, 'Jury', false);
    await upto(p, '71_deve_valide_soutenance', form);
  },
  async '46_jury_eligibilite'() {
    const p = await open(await as('sbonnet'), these('review.php', { dossierid: ids.zoe }));
    await region(p, '46_jury_eligibilite', fieldset('Jury'));
  },
  async '40_suivi_signatures'() {
    const p = await open(await as('sbonnet'), these('signatures.php', { dossierid: ids.camille }));
    await upto(p, '40_suivi_signatures');
  },
  async '41_signature_sur_place'() {
    // Vérification de l'identité, puis page de signature (document à signer en tête).
    const p = await open(await as('sbonnet'), these('sign.php', { dossierid: ids.camille, slot: 'ccem' }));
    await check(p, '#id_identityverified');
    await upto(p, '41_verification_identite');
    await Promise.all([p.waitForNavigation(), p.click('#id_submitbutton')]);
    await grow(p);
    await draw(p);
    await check(p, '#id_signatureconsent');
    await upto(p, '41_signature_sur_place');
  },
  async '83_dossier_complet'() {
    const p = await open(await as('sbonnet'), these('dossier.php', { dossierid: ids.camille }));
    await uptoSection(p, '83_dossier_complet', 'Soutenance');
    const [jury] = await sectionSel(p, 'Jury');
    const [, signed] = await sectionSel(p, 'Documents signés');
    await region(p, '83_dossier_complet_suite', jury, signed);
  },
  async '73_deve_documents'() {
    const p = await open(await as('sbonnet'), these('documents.php', { dossierid: ids.camille }));
    await upto(p, '73_deve_documents');
  },
  async '30_convocation'() {
    const p = await as('sbonnet');
    const base = `/pluginfile.php/${ids.contextid}/mod_these/generated/${ids.camille}/`;
    fs.copyFileSync(await pdfPage(p, url(base + 'convocation-jury-1-jean-dupont.pdf'), 'conv', 'conv.pdf'),
      path.join(OUT, '30_convocation.png'));
    fs.copyFileSync(await pdfPage(p, url(base + 'proces-verbal.pdf'), 'pv', 'pv.pdf'), path.join(OUT, '31_proces_verbal.png'));
    console.log('capture 30_convocation, 31_proces_verbal');
  },
  async '42_documents_signes'() {
    const p = await as('sbonnet');
    const a = await pdfPage(p, these('document.php', { dossierid: ids.camille, type: 'subject' }), 'subject', 'subject.pdf');
    const b = await pdfPage(p, these('document.php', { dossierid: ids.camille, type: 'agreement' }), 'agreement', 'agreement.pdf');
    execFileSync('convert', [a, b, '-bordercolor', '#B0B7BF', '-border', '1', '+append', path.join(OUT, '42_documents_signes.png')]);
    console.log('capture 42_documents_signes');
  },
  async '93_document_scelle'() {
    const p = await open(await as('sbonnet'), these('dossier.php', { dossierid: ids.camille }));
    const [h, n] = await sectionSel(p, 'Historique');
    await p.evaluate(() => {
      const rows = [...document.querySelectorAll('[data-shot="n-Historique"] tbody tr, table[data-shot="n-Historique"] tbody tr')];
      const i = rows.findIndex((r) => /scellé/i.test(r.textContent));
      if (i >= 0 && rows[i + 1]) {
        rows[i + 1].setAttribute('data-shot', 'last');
      } else if (i >= 0) {
        rows[i].setAttribute('data-shot', 'last');
      }
    });
    await region(p, '93_document_scelle', h, '[data-shot="last"]');
  },
  async '64_agent_mes_signatures'() {
    const p = await open(await as('agarnier'), these('mysignatures.php'));
    await upto(p, '64_agent_mes_signatures');
  },
  async '65_agent_signature_enregistree'() {
    // Première signature de l'agent sur un seul document, en enregistrant sa signature.
    const p = await open(await as('agarnier'), these('mysignatures.php'));
    // Aucun document n'est coché d'office : l'agent coche le premier.
    const docs = p.locator(`${form} input[type=checkbox][name^="sig"]:not([name="signatureconsent"])`);
    await docs.first().check();
    await draw(p);
    await check(p, '#id_savesignature');
    await check(p, '#id_signatureconsent');
    await Promise.all([p.waitForNavigation(), p.click(`${form} input[type=submit]:not([name=cancel])`)]);
    await open(p, these('mysignatures.php'));
    await upto(p, '65_agent_signature_enregistree');
  },
  async '66_president_mes_signatures'() {
    const p = await open(await as('jdupont'), these('mysignatures.php'));
    await upto(p, '66_president_mes_signatures');
  },
  async '50_acces_jury'() {
    const p = await open(await as(null), these('jury.php', { token: ids.juryaccess }));
    await upto(p, '50_acces_jury');
  },
  async '54_pv_decisions'() {
    const p = await open(await as('sbonnet'), these('minutes.php', { dossierid: ids.theo }));
    await upload(p, 'pv', samplePdf('pv-signe.pdf', 'Procès-verbal signé'));
    await p.selectOption('#id_defenseresult', '1');
    await p.selectOption('#id_mention', 'treshonorable');
    await check(p, '#id_prizeproposal');
    await check(p, '#id_correctionsrequested');
    await p.fill('#id_correctionsnote', 'Compléter la discussion sur les biais de recrutement ; harmoniser la bibliographie.');
    await upto(p, '54_pv_decisions', form);
  },
  async '79_pv_ajournement'() {
    const p = await open(await as('sbonnet'), these('minutes.php', { dossierid: ids.theo }));
    await upload(p, 'pv', samplePdf('pv-signe.pdf', 'Procès-verbal signé'));
    await p.selectOption('#id_defenseresult', '2');
    await p.fill('#id_remark', "Le jury demande de reprendre l'analyse statistique.");
    await upto(p, '79_pv_ajournement', form);
  },
  async '80_dossier_ajourne'() {
    const p = await open(await as('sbonnet'), these('dossier.php', { dossierid: ids.sarah }));
    await uptoSection(p, '80_dossier_ajourne', 'Soutenance');
  },
  async '78_dossier_clos'() {
    const p = await open(await as('sbonnet'), these('review.php', { dossierid: ids.maxime }));
    await Promise.all([p.waitForNavigation(), p.click('#id_validatestep')]);
    await grow(p);
    await uptoSection(p, '78_dossier_clos', 'Corrections');
  },
  async '95_deve_creer_dossier'() {
    const p = await open(await as('sbonnet'), these('create.php'));
    await upto(p, '95_deve_creer_dossier');
  },
  async '96_deve_abandon_dossier'() {
    const p = await open(await as('sbonnet'), these('cancel.php', { dossierid: ids.lucas }));
    await p.locator(`${form} textarea`).first().fill("L'étudiant renonce à sa thèse d'exercice (courriel du 20 octobre 2026).");
    await upto(p, '96_deve_abandon_dossier', form);
  },
  async '85_sujets_precedents'() {
    const p = await open(await as('sbonnet'), these('dossier.php', { dossierid: ids.romain }));
    const [h1, n1] = await sectionSel(p, 'Sujets précédents');
    await region(p, 'tmp_85a', h1, n1);
    const [h2, n2] = await sectionSel(p, 'Historique');
    await region(p, 'tmp_85b', h2, n2);
    execFileSync('convert', [path.join(OUT, 'tmp_85a.png'), path.join(OUT, 'tmp_85b.png'), '-background', 'white', '-append',
      path.join(OUT, '85_sujets_precedents.png')]);
    fs.unlinkSync(path.join(OUT, 'tmp_85a.png'));
    fs.unlinkSync(path.join(OUT, 'tmp_85b.png'));
    console.log('capture 85_sujets_precedents');
  },
  async '98_deve_scan_depot'() {
    const p = await open(await as('sbonnet'), these('scan.php', { dossierid: ids.theo }));
    await p.selectOption('#id_doctype', 'contract');
    await p.waitForTimeout(500);
    await check(p, '#id_sign_contract_ucbl');
    await p.selectOption('#id_signeddate_day', '22');
    await p.selectOption('#id_signeddate_month', '10');
    await p.selectOption('#id_signeddate_year', '2026');
    await upload(p, 'scan', path.join(HERE, 'scan-contrat-signe.png'));
    await p.fill('#id_remark', 'Contrat visé sur papier, reçu par courrier interne.');
    await upto(p, '98_deve_scan_depot', form);
    await Promise.all([p.waitForNavigation(), p.click('#id_submitbutton')]);
    await grow(p);
    await section(p, '99_dossier_scans', 'Soutenance');
    const [h] = await sectionSel(p, 'Historique');
    await region(p, '100_historique_scan', h, '[data-shot="n-Historique"] tbody tr:first-child');
  },
  async '74_deve_transmis'() {
    const p = await open(await as('sbonnet'), these('documents.php', { dossierid: ids.camille }));
    await Promise.all([p.waitForNavigation(), p.getByRole('button', { name: "Transmettre au jury et à l'université" }).click()]);
    await grow(p);
    await uptoSection(p, '74_deve_transmis', 'Sujet de thèse');
  },

  // --- Bibliothèque ---------------------------------------------------------------------------
  async '07_bibliotheque_notice'() {
    const p = await open(await as('blefort'), these('review.php', { dossierid: ids.jules }));
    await p.locator(`${form} input[type=text]`).first().fill('SUDOC-124387');
    await upto(p, '07_bibliotheque_notice', form);
  },
  async '72_biblio_conformite'() {
    const p = await open(await as('blefort'), these('review.php', { dossierid: ids.manon }));
    const boxes = p.locator(`${form} input[type=checkbox]:not(#id_savesignature)`);
    for (let i = 0; i < await boxes.count(); i++) {
      if (!(await boxes.nth(i).isChecked())) {
        await boxes.nth(i).check();
      }
    }
    await upto(p, '72_biblio_conformite', form);
  },
  async '97_biblio_corrections'() {
    const p = await open(await as('blefort'), these('review.php', { dossierid: ids.pauline }));
    await upto(p, '97_biblio_corrections', form);
  },

  // --- Paramètres (modèles, grille et questionnaire : dans l'activité de suivi, qui les partage) -----
  async '43_modeles_documents'() {
    const p = await open(await as('sbonnet'), synthesis('doctemplates.php'));
    await upto(p, '43_modeles_documents');
  },
  async '24_parametres_circuit'() {
    const p = await open(await as('sbonnet'), synthesis('parameters.php'));
    await upto(p, '24_parametres_circuit', form);
  },
  async '44_modele_edition'() {
    const p = await open(await as('sbonnet'), synthesis('doctemplates.php', { type: 'subject' }));
    await upto(p, '44_modele_edition', form);
  },
  async '48_grille_elements'() {
    const p = await open(await as('sbonnet'), synthesis('griditems.php'));
    // Début de la liste : la page compte une trentaine d'éléments.
    await p.evaluate(() => {
      const items = document.querySelectorAll('#region-main form.mform .fitem');
      items[Math.min(11, items.length - 1)].setAttribute('data-shot', 'grid-end');
    });
    await upto(p, '48_grille_elements', '[data-shot="grid-end"]');
  },
  async '51_questionnaire_questions'() {
    const p = await open(await as('sbonnet'), synthesis('questions.php'));
    await upto(p, '51_questionnaire_questions');
  },
  async '45_transfert_parametres'() {
    const p = await open(await as('sbonnet'), these('settingstransfer.php'));
    await upto(p, '45_transfert_parametres');
  },
  async '23_parametres'() {
    const p = await open(await as('sbonnet'), url(`/course/modedit.php?update=${CM}`));
    await upto(p, '23_parametres');
  },
  async '20_import'() {
    const p = await open(await as('sbonnet'), these('import.php'));
    await upto(p, '20_import');
  },
  async '19_courriels'() {
    const p = await open(await as('sbonnet'), these('notifications.php'));
    await upto(p, '19_courriels');
  },
  async '47_annuaire_jury'() {
    const p = await open(await as('sbonnet'), these('jurors.php'));
    await upto(p, '47_annuaire_jury');
  },

  // --- Suivi des thèses -----------------------------------------------------------------------
  async '49_suivi_theses'() {
    const p = await open(await as('hdurand'), synthesis('view.php'));
    await uptoSection(p, '49_suivi_theses', 'Mes jurys');
  },
  async '56_recherche_theses'() {
    const p = await open(await as('sbonnet'), synthesis('search.php', { q: 'leptospirose', search: 1 }));
    await upto(p, '56_recherche_theses');
  },
  async '90_recherche_confidentielle'() {
    const p = await open(await as('ncolin'), synthesis('search.php', { year: 2026, search: 1 }));
    await upto(p, '90_recherche_confidentielle');
  },
};

(async () => {
  browser = await chromium.launch();
  const wanted = process.argv.slice(2);
  const names = wanted.length ? wanted : Object.keys(steps);
  const failed = [];
  try {
    for (const name of names) {
      if (!steps[name]) {
        throw new Error('Capture inconnue : ' + name);
      }
      try {
        await steps[name]();
      } catch (e) {
        failed.push(name);
        console.log('ÉCHEC', name, String(e.message).split('\n')[0]);
      }
      for (const ctx of browser.contexts()) {
        await ctx.close();
      }
    }
  } finally {
    for (const ctx of browser.contexts()) {
      await ctx.close();
    }
    await browser.close();
  }
  if (failed.length) {
    console.log('Échecs :', failed.join(' '));
    process.exitCode = 1;
  }
})();
