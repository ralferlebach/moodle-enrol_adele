// AdeLe – überarbeitete Präsentation (Stand 2026-10-07, Plugin-Stand 2026100708)
const pptxgen = require("pptxgenjs");
const React = require("react");
const ReactDOMServer = require("react-dom/server");
const sharp = require("sharp");
const fa = require("react-icons/fa");
const SKILL = "/root/.claude/skills/synced/833cf69c-be63-42a1-b41f-3e39160abff6_aeae3714-ea79-4e6c-a749-d2a24fb9c37c/pptx";
const { applyTheme } = require(SKILL + "/scripts/apply_theme.js");

const OUT = process.argv[2] || "AdeLe_Stand_2026-10-07_v2.pptx";

const THEME = {
  name: "AdeLe",
  headFontFace: "Cambria",
  bodyFontFace: "Calibri",
  colors: {
    dk1: "1B2A3A", lt1: "FFFFFF", dk2: "5B6770", lt2: "F4F6F8",
    accent1: "0E7C86", accent2: "2BB673", accent3: "1E7A4A",
    accent4: "C2410C", accent5: "D6EEF0", accent6: "DDE3E8",
    hlink: "0E7C86", folHlink: "5B6770",
  },
};
const H = THEME.colors;

async function icon(name, color = "FFFFFF") {
  const svg = ReactDOMServer.renderToStaticMarkup(React.createElement(fa[name], { color: "#" + color, size: 256 }));
  const buf = await sharp(Buffer.from(svg)).resize(256, 256, { fit: "contain", background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toBuffer();
  return "image/png;base64," + buf.toString("base64");
}

(async () => {
  const pres = new pptxgen();
  pres.layout = "LAYOUT_WIDE"; // 13.333 x 7.5
  pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
  pres.title = "AdeLe – Teststrategie, Rechteabdeckung, Userstory-Tests und Befunde";
  pres.author = "AdeLe-Projekt";
  const C = pres.SchemeColor;
  const NAVY = C.text1, MUTED = C.text2, WHITE = C.background1, LIGHT = C.background2;
  const TEAL = C.accent1, GREEN = C.accent2, DGREEN = C.accent3, ORANGE = C.accent4, TINT = C.accent5, BORDER = C.accent6;

  const W = 13.333, MX = 0.6, CW = W - 2 * MX;

  // ---------- Layouts ----------
  pres.defineSlideMaster({
    title: "TITLE_DARK",
    background: { color: NAVY },
    objects: [
      { placeholder: { options: { name: "title", type: "title", x: 0.8, y: 2.2, w: 8.6, h: 1.0, fontSize: 48, bold: true, color: WHITE, align: "left", valign: "top", margin: 0 }, text: "" } },
      { placeholder: { options: { name: "body", type: "body", x: 0.8, y: 3.3, w: 8.6, h: 1.3, fontSize: 20, color: TINT, align: "left", valign: "top", margin: 0 }, text: "" } },
    ],
  });
  pres.defineSlideMaster({
    title: "CONTENT",
    background: { color: WHITE },
    margin: [0.5, 0.6, 0.6, 0.6],
    objects: [
      { placeholder: { options: { name: "title", type: "title", x: MX, y: 0.38, w: CW, h: 0.62, fontSize: 27, bold: true, color: NAVY, align: "left", valign: "middle", margin: 0 }, text: "" } },
      { placeholder: { options: { name: "body", type: "body", x: MX, y: 1.0, w: CW, h: 0.36, fontSize: 13.5, color: MUTED, align: "left", valign: "top", margin: 0 }, text: "" } },
      { text: { text: "AdeLe · enrol_adele · local_adele · mod_adele · Stand 7. Oktober 2026", options: { x: MX, y: 7.08, w: 8, h: 0.25, fontSize: 9, color: MUTED, margin: 0 } } },
    ],
    slideNumber: { x: W - MX - 0.6, y: 7.08, w: 0.6, h: 0.25, fontSize: 9, color: MUTED, align: "right" },
  });

  // ---------- Helpers ----------
  const T = (s, text, o) => s.addText(text, Object.assign({ isTextBox: true, margin: 0, color: NAVY, fontSize: 11, valign: "top" }, o));
  const card = (s, x, y, w, h, o = {}) => s.addShape(pres.shapes.ROUNDED_RECTANGLE, Object.assign({ x, y, w, h, rectRadius: 0.06, fill: { color: LIGHT }, line: { color: BORDER, width: 0.75 } }, o));
  async function iconDot(s, name, x, y, d, color = TEAL) {
    s.addShape(pres.shapes.OVAL, { x, y, w: d, h: d, fill: { color }, line: { color, width: 0 } });
    s.addImage({ data: await icon(name), x: x + d * 0.26, y: y + d * 0.26, w: d * 0.48, h: d * 0.48 });
  }
  const label = (s, text, x, y, w, color = TEAL) => T(s, text, { x, y, w, h: 0.24, fontSize: 10.5, bold: true, color, charSpacing: 1.5 });

  // =====================================================================
  // 1 · Titel
  // =====================================================================
  pres.addSection({ title: "Titel" });
  {
    const s = pres.addSlide({ masterName: "TITLE_DARK", sectionTitle: "Titel" });
    s.addShape(pres.shapes.OVAL, { x: 10.2, y: -1.3, w: 4.6, h: 4.6, fill: { color: TEAL, transparency: 25 }, line: { color: TEAL, width: 0 } });
    s.addShape(pres.shapes.OVAL, { x: 11.3, y: 4.6, w: 3.4, h: 3.4, fill: { color: GREEN, transparency: 35 }, line: { color: GREEN, width: 0 } });
    s.addText("AdeLe", { placeholder: "title" });
    s.addText("Teststrategie, Rechteabdeckung, Userstory-Tests und Befunde der Testphase", { placeholder: "body" });
    T(s, "enrol_adele · local_adele · mod_adele", { x: 0.8, y: 4.75, w: 8.6, h: 0.35, fontSize: 14, color: WHITE });
    T(s, "Überarbeitete Fassung · Stand 7. Oktober 2026 · Plugin-Stand 2026100708", { x: 0.8, y: 6.6, w: 9, h: 0.3, fontSize: 11, color: TINT });
    s.addNotes("Überarbeitete Fassung des Erstentwurfs. Sechs Inhaltsfolien: Testarten, Rechtematrix gegen Tests, Userstory-Ansatz im Vergleich zu local_adele #470, alle Cluster und Userstories, Stand und Dauer, Befunde und Fixes.");
  }

  // =====================================================================
  // 2 · Testarten nach Zweck
  // =====================================================================
  pres.addSection({ title: "Teststrategie" });
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Teststrategie" });
    s.addText("Welche Tests die Plugins absichern", { placeholder: "title" });
    s.addText("Nach Zweck gebündelt. Drei Testarten tragen die Funktionssicherung, vier weitere decken andere Zwecke ab.", { placeholder: "body" });

    label(s, "FUNKTIONSFÄHIGKEIT SICHERN", MX, 1.5, 6);
    const gap = 0.25, w3 = (CW - 2 * gap) / 3, y0 = 1.8, h0 = 3.05;
    const main = [
      { ic: "FaCubes", name: "Unit-Tests", q: "Stimmt die einzelne Regel?", big: "779", unit: "Tests",
        sub: "PHPUnit 433 (local 354 · enrol 57 · mod 22) · Jest 346 für die Vue-Bausteine",
        a: "Serverlogik, Webservices, Rechte, Ereignisse, Upgrade-Schritte; im Frontend Komponenten und Store.",
        b: "Kennt keine Oberfläche und kein Zusammenspiel der drei Plugins." },
      { ic: "FaClipboardList", name: "Behat", q: "Trägt der Ablauf in Moodle?", big: "21", unit: "Szenarien",
        sub: "local 13 in 5 Features · enrol 8 · läuft in der Plugin-CI bei jedem Push",
        a: "Abläufe in Moodles eigener Testumgebung, als lesbare Schritte: Einstellungen, Editor, Lernendenansicht.",
        b: "Bisher nur Positivfälle und drei Konten (Admin, Student, eine Lehrkraft)." },
      { ic: "FaRoute", name: "Playwright-Userstories", q: "Erreicht ein Mensch das Ziel?", big: "62", unit: "Tests",
        sub: "26 Specs (local 43 · mod 13 · enrol 6) · von Hand gestarteter Workflow mit Video",
        a: "Ganze Userstory über alle drei Plugins: Oberfläche, Serverantwort, Einschreibung, realer Kurszugriff, Negativkontrolle.",
        b: "Langsamste Stufe; braucht Fixtures und die Server-Testuhr." },
    ];
    for (let i = 0; i < 3; i++) {
      const m = main[i], x = MX + i * (w3 + gap);
      card(s, x, y0, w3, h0, { fill: { color: LIGHT }, line: { color: TEAL, width: 1.5 } });
      await iconDot(s, m.ic, x + 0.22, y0 + 0.2, 0.62);
      T(s, m.name, { x: x + 1.0, y: y0 + 0.18, w: w3 - 1.15, h: 0.36, fontSize: 18, bold: true, fontFace: "Cambria" });
      T(s, m.q, { x: x + 1.0, y: y0 + 0.54, w: w3 - 1.15, h: 0.28, fontSize: 12.5, italic: true, color: TEAL });
      T(s, [{ text: m.big, options: { fontSize: 40, bold: true, color: TEAL, fontFace: "Cambria" } }, { text: "  " + m.unit, options: { fontSize: 14, color: MUTED } }],
        { x: x + 0.22, y: y0 + 0.95, w: w3 - 0.44, h: 0.62, valign: "bottom" });
      T(s, m.sub, { x: x + 0.22, y: y0 + 1.6, w: w3 - 0.44, h: 0.42, fontSize: 10.5, color: MUTED });
      T(s, [{ text: "Prüft: ", options: { bold: true } }, { text: m.a, options: { breakLine: true } },
            { text: "Grenze: ", options: { bold: true } }, { text: m.b }],
        { x: x + 0.22, y: y0 + 2.07, w: w3 - 0.44, h: 0.92, fontSize: 10.5, paraSpaceAfter: 3 });
    }

    label(s, "WEITERE ZWECKE", MX, 5.03, 6, MUTED);
    const w4 = (CW - 3 * gap) / 4, y1 = 5.32, h1 = 1.55;
    const other = [
      { ic: "FaUniversalAccess", name: "Barrierefreiheit", t: "axe-Prüfungen nach WCAG 2.1 AA in Playwright. Screenreader-Suite mit NVDA ist gebaut; der Lauf unter Windows steht aus." },
      { ic: "FaTachometerAlt", name: "Last", t: "k6 und JMeter gegen die Lese-Endpunkte, mit Seed für große Datenmengen. Start von Hand." },
      { ic: "FaCode", name: "Codequalität", t: "Plugin-CI bei jedem Push: phpcs ohne Warnungen, PHPDoc, Lint, PHPMD, Mustache. Wächtertests für Uhrzugriffe und Versionsabhängigkeiten." },
      { ic: "FaFire", name: "Smoke und Regression", t: "22 ältere Playwright-Tests in 7 Specs als schneller Rauchtest: wöchentlich und von Hand." },
    ];
    for (let i = 0; i < 4; i++) {
      const m = other[i], x = MX + i * (w4 + gap);
      card(s, x, y1, w4, h1, { fill: { color: WHITE } });
      await iconDot(s, m.ic, x + 0.18, y1 + 0.17, 0.42, MUTED);
      T(s, m.name, { x: x + 0.72, y: y1 + 0.17, w: w4 - 0.85, h: 0.42, fontSize: 13.5, bold: true, valign: "middle" });
      T(s, m.t, { x: x + 0.18, y: y1 + 0.68, w: w4 - 0.36, h: 0.82, fontSize: 10 });
    }
    s.addNotes("Zahlen aus dem letzten Gesamtlauf (Plugin-Stand 2026100708): PHPUnit local 354 (1 übersprungen, 1 unvollständig), enrol 57, mod 22; Jest 346 Tests in 73 Suiten; Playwright-Userstories enrol 6, mod 13, local 43. Behat: 21 Szenarien (local 13, enrol 8). Smoke: local 14, mod 4, enrol 4. Für die Funktionsfähigkeit maßgeblich: Unit (Regel stimmt), Behat (Ablauf in Moodle), Playwright (Userstory mit realer Wirkung).");
  }

  // =====================================================================
  // 3 · Rechtematrix gegen Tests
  // =====================================================================
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Teststrategie" });
    s.addText("Rechtematrix: Welche Tests prüfen welches Recht", { placeholder: "title" });
    s.addText("Zwölf Fähigkeiten der Soll-Ist-Matrix, je Testart: welche Rollen mit Erlaubnis (+) und mit Ablehnung (−) geprüft sind.", { placeholder: "body" });

    // status: 2 = beide Richtungen, 1 = eine Richtung, 0 = nicht geprüft
    const rows = [
      ["Dashboard / Navbar öffnen", ["M+ BP+ ET+ L−", 2], ["Adm+", 1], ["M+ A+ ET− T−", 2]],
      ["Lernpfad anlegen", ["BP+ L−", 2], ["Adm+", 1], ["Adm+ A+ L−", 2]],
      ["Lernpfad bearbeiten / speichern", ["M+ BP± ET+ L−", 2], ["Adm+", 1], ["M+ A+ BP± L−", 2]],
      ["Sichtbarkeit umschalten", ["BP−", 1], ["nicht geprüft", 0], ["L− (Schalter nie betätigt)", 1]],
      ["Benannte Personen verwalten", ["M+ BP±", 2], ["nicht geprüft", 0], ["M+", 1]],
      ["Duplizieren / Löschen / Bild", ["M+ A+ BP± L−", 2], ["nicht geprüft", 0], ["Adm+ A+ L− (ohne Bild)", 2]],
      ["Completion & Zugang abhaken", ["M+ ET± T− L−", 2], ["nicht geprüft", 0], ["ET± T− BP− A− L−", 2]],
      ["Eigenen Fortschritt sehen / setzen", ["L±", 2], ["L+", 1], ["L±", 2]],
      ["Aktivität zum Kurs hinzufügen", ["nicht geprüft", 0], ["nicht geprüft", 0], ["Adm+", 1]],
      ["Teacher-Steuerung in Aktivität", ["M+ ET± T± BP+ A− L−", 2], ["ET+", 1], ["alle sechs Rollen ±", 2]],
      ["Aktivität ansehen (Student-View)", ["L+", 1], ["L+", 1], ["L+ BP+ M− A−", 2]],
      ["Sichtbare Lernpfade sehen", ["M+ A+ BP+ ET+ L−", 2], ["Adm+", 1], ["A± M+", 2]],
    ];
    const fillFor = (st) => st === 2 ? { color: GREEN, transparency: 78 } : st === 1 ? { color: TEAL, transparency: 88 } : { color: BORDER, transparency: 45 };
    const hdr = (t, al = "left") => ({ text: t, options: { bold: true, color: WHITE, fill: { color: NAVY }, fontSize: 11, align: al, valign: "middle" } });
    const tbl = [[hdr("#", "center"), hdr("Fähigkeit laut Matrix"), hdr("Unit (PHPUnit)"), hdr("Behat"), hdr("Playwright")]];
    rows.forEach((r, i) => {
      const cell = (c) => ({ text: c[0], options: { fill: fillFor(c[1]), fontSize: 10, color: c[1] === 0 ? MUTED : NAVY, italic: c[1] === 0, valign: "middle" } });
      tbl.push([
        { text: String(i + 1), options: { fontSize: 10, color: MUTED, align: "center", valign: "middle" } },
        { text: r[0], options: { fontSize: 10.5, bold: true, valign: "middle" } },
        cell(r[1]), cell(r[2]), cell(r[3]),
      ]);
    });
    const tx = MX, ty = 1.5, tw = 8.25;
    s.addTable(tbl, { x: tx, y: ty, w: tw, colW: [0.4, 2.75, 1.95, 1.15, 2.0], rowH: 0.375, border: { type: "solid", color: H.accent6, pt: 0.75 }, color: NAVY, margin: [0.02, 0.08, 0.02, 0.08], objectName: "Rechte-Abdeckung" });

    // Legende
    const ly = 6.5;
    const leg = [[fillFor(2), "Erlaubnis und Ablehnung geprüft"], [fillFor(1), "nur eine Richtung"], [fillFor(0), "nicht geprüft"]];
    let lx = tx;
    leg.forEach(([f, t], i) => {
      s.addShape(pres.shapes.RECTANGLE, { x: lx, y: ly + 0.02, w: 0.26, h: 0.18, fill: f, line: { color: BORDER, width: 0.75 } });
      T(s, t, { x: lx + 0.33, y: ly, w: 2.3, h: 0.22, fontSize: 9.5, color: MUTED, valign: "middle" });
      lx += [2.75, 1.75, 1.5][i];
    });
    T(s, "M Adele Manager · A Adele Assistant · ET Editing Teacher · T Trainer:in · BP Benannte Person · L Lerner:in · Adm Administrator (nicht Teil der Matrix)",
      { x: tx, y: ly + 0.26, w: tw, h: 0.22, fontSize: 9.5, color: MUTED });

    // Seitenleiste
    const sx = tx + tw + 0.3, sw = W - MX - sx;
    const stats = [
      ["17 von 119", "PHPUnit-Dateien prüfen Rollenergebnisse zu diesen Rechten (local 16, mod 1, enrol 0)."],
      ["12 von 21", "Behat-Szenarien berühren ein Recht. Keines prüft eine Ablehnung."],
      ["10 von 33", "Playwright-Specs prüfen Rollenunterschiede oder Ablehnungen, als einzige mit echten Systemrollen."],
    ];
    stats.forEach(([n, t], i) => {
      const y = ty + i * 0.92;
      card(s, sx, y, sw, 0.8);
      T(s, n, { x: sx + 0.15, y, w: 1.45, h: 0.8, fontSize: 19, bold: true, color: TEAL, fontFace: "Cambria", valign: "middle" });
      T(s, t, { x: sx + 1.6, y, w: sw - 1.72, h: 0.8, fontSize: 9.5, valign: "middle" });
    });
    const gy = ty + 3 * 0.92 + 0.05;
    label(s, "LÜCKEN", sx, gy, sw, ORANGE);
    T(s, [
      { text: "Aktivität hinzufügen (9): nur als Administrator, keine Ablehnung.", options: { bullet: true, breakLine: true } },
      { text: "Sichtbarkeit (4): Der Schalter wird in keinem Test betätigt.", options: { bullet: true, breakLine: true } },
      { text: "Trainer:in kommt nur bei 7 und 10 vor.", options: { bullet: true, breakLine: true } },
      { text: "Benannte Person ohne Rolle: nie in der Editor-Oberfläche geprüft.", options: { bullet: true, breakLine: true } },
      { text: "Behat: keine Negativfälle, drei Konten.", options: { bullet: true } },
    ], { x: sx, y: gy + 0.28, w: sw, h: 1.72, fontSize: 10, paraSpaceAfter: 3 });
    T(s, "Die Matrix beschreibt local_adele v2026031200. Seither geändert: #586, #587, #588 und mod_adele #39.",
      { x: sx, y: 6.42, w: sw, h: 0.52, fontSize: 9.5, italic: true, color: MUTED });
    s.addNotes("Grundlage: Blatt „Rechte-Matrix“ der Datei ADELE_Rollen_Rechte_Soll-Ist (Stand local_adele v2026031200), abgeglichen mit den Testdateien der drei Plugins. + heißt: Test belegt, dass die Rolle die Fähigkeit hat; − heißt: Test belegt die Ablehnung; ± beides. PHPUnit: 119 Testdateien, davon 17 mit Rollenergebnissen zu den zwölf Fähigkeiten. Behat: 21 Szenarien, 12 berühren ein Recht, keine Ablehnung. Playwright: 33 Specs (Smoke und Userstories), 30 berühren ein Recht, 10 mit Rollenunterschied oder Ablehnung. Nicht durch Rollentests gedeckt sind außerdem die Dienste update_user_path_relation und get_editable_learning_paths.");
  }

  // =====================================================================
  // 4 · Userstory-Ansatz und Unterschied zu #470
  // =====================================================================
  pres.addSection({ title: "Userstory-Tests" });
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Userstory-Tests" });
    s.addText("Userstory-getriebene Tests mit Playwright", { placeholder: "title" });
    s.addText("Links der Ansatz und seine 14 Cluster, rechts der Unterschied zum manuellen Testplan aus local_adele #470.", { placeholder: "body" });

    const lw = 5.75, rx = MX + lw + 0.4, rw = W - MX - rx;
    label(s, "DER ANSATZ", MX, 1.5, lw);
    const pts = [
      ["FaStream", "Story wird Zustandskette", "Jede Userstory des Testplans läuft als Kette von Zuständen; jeder Zustand wird geprüft."],
      ["FaBullseye", "Reale Wirkung", "Oberfläche, Serverantwort, Einschreibung und realer Kurszugriff, nicht nur ein Datenbankeintrag."],
      ["FaBan", "Negativkontrolle", "Jede Kette zeigt auch, wen die Änderung nicht treffen darf."],
      ["FaUser", "Wie ein Mensch", "Echtes Drag&Drop, Formulare, Tastatur; je Rolle eine feste Person aus den Fixtures."],
      ["FaVideo", "Nachweis", "Video, Trace und Bericht bei jedem Lauf; Zeitabläufe über die Server-Testuhr."],
    ];
    for (let i = 0; i < pts.length; i++) {
      const y = 1.83 + i * 0.66;
      await iconDot(s, pts[i][0], MX, y + 0.03, 0.46);
      T(s, [{ text: pts[i][1], options: { bold: true, fontSize: 12.5, breakLine: true } }, { text: pts[i][2], options: { fontSize: 10.5, color: MUTED } }],
        { x: MX + 0.62, y, w: lw - 0.62, h: 0.6, valign: "middle" });
    }
    label(s, "DIE 14 CLUSTER", MX, 5.2, lw);
    const cl = [["R", "Rollen"], ["E", "Editor"], ["C", "Zugang"], ["T", "Zeit"], ["F", "Feedback"], ["L", "Fortschritt"], ["O", "Master"],
                ["D", "Diagnose"], ["A", "Routen"], ["M", "Änderung"], ["H", "Einbindung"], ["P", "Lernerdaten"], ["Z", "Abschluss"], ["V", "Vertrag"]];
    const cwid = lw / 7;
    cl.forEach(([k, n], i) => {
      const x = MX + (i % 7) * cwid, y = 5.5 + Math.floor(i / 7) * 0.72;
      s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y, w: cwid - 0.08, h: 0.62, rectRadius: 0.05, fill: { color: TINT }, line: { color: TINT, width: 0 } });
      T(s, [{ text: k, options: { bold: true, fontSize: 14, color: TEAL, fontFace: "Cambria", breakLine: true } }, { text: n, options: { fontSize: 8.5 } }],
        { x, y, w: cwid - 0.08, h: 0.62, align: "center", valign: "middle" });
    });

    // rechte Spalte
    s.addShape(pres.shapes.LINE, { x: rx - 0.2, y: 1.5, w: 0, h: 5.4, line: { color: BORDER, width: 1 } });
    label(s, "UNTERSCHIED ZU LOCAL_ADELE #470", rx, 1.5, rw);
    const hd = (t, col) => ({ text: t, options: { bold: true, color: WHITE, fill: { color: col }, fontSize: 10.5, valign: "middle" } });
    const k = (t) => ({ text: t, options: { bold: true, fontSize: 10, color: MUTED, valign: "middle" } });
    const a = (t) => ({ text: t, options: { fontSize: 10, valign: "middle" } });
    const b = (t) => ({ text: t, options: { fontSize: 10, valign: "middle", fill: { color: TINT, transparency: 45 } } });
    const cmp = [
      [hd("", NAVY), hd("#470 „Manual Test Plan“", NAVY), hd("Userstory-Ketten", TEAL)],
      [k("Form"), a("Checkliste mit 53 Story-Karten zum Abhaken"), b("62 ausführbare Tests in 26 Specs")],
      [k("Ausführung"), a("Von Hand, eine Person mit Admin-Login"), b("Automatisch, lokal und als Workflow")],
      [k("Gliederung"), a("Epics A–G nach Funktionsbereich, dazu fünf Kombinationsszenarien"), b("14 Cluster nach Risiko, je Story eine Zustandskette")],
      [k("Rollen"), a("Testkonten von Hand anlegen und wechseln"), b("Feste Person je Rolle aus den Fixtures")],
      [k("Prüftiefe"), a("Sichtprüfung, Pass oder Fail je Karte"), b("Oberfläche, Serverantwort, Einschreibung, Zugriff, Persistenz, Negativkontrolle")],
      [k("Zeit"), a("Cron von Hand anstoßen"), b("Server-Testuhr: vor, im und nach dem Fenster")],
      [k("Nachweis"), a("Haken und Notiz; 27 von 53 Karten abgehakt, Issue geschlossen"), b("Video, Trace und Bericht bei jedem Lauf")],
      [k("Wiederholung"), a("Jeder Durchlauf kostet erneut Handarbeit"), b("Beliebig oft, nach jedem Fix als Gegenprobe")],
    ];
    s.addTable(cmp, { x: rx, y: 1.82, w: rw, colW: [1.1, 2.4, rw - 3.5], rowH: [0.36, 0.44, 0.44, 0.56, 0.44, 0.62, 0.44, 0.56, 0.44], border: { type: "solid", color: H.accent6, pt: 0.75 }, color: NAVY, margin: [0.02, 0.08, 0.02, 0.08], objectName: "Vergleich #470" });
    T(s, "Beides ergänzt sich: #470 beschreibt, was ein Mensch sehen soll. Die Ketten belegen es bei jedem Lauf.",
      { x: rx, y: 6.3, w: rw, h: 0.5, fontSize: 11, italic: true, color: TEAL });
    s.addNotes("local_adele #470 (Titel „E2E“, von cbadusch am 29.06.2026 angelegt, geschlossen) enthält den „AdeLe — Manual Test Plan“: eine Checkliste für eine Testperson mit Admin-Login, gegliedert in einen Smoke-Durchgang, die Epics A (Site-Konfiguration), B (Zugriff und Rollen), C (Lernpfad bauen), D (in Kurs veröffentlichen), E (Lernende), F (Lehrkraft-Monitoring), G (Sprache und Onboarding), Kombinationsszenarien und Sign-off. 53 Story-Karten, davon 27 abgehakt. Die Userstory-Ketten folgen dem Testplan in enrol_adele/docs/testplan/AdeLe_E2E_Testplan_UserStories.md.");
  }

  // =====================================================================
  // 5 · Alle Cluster und Userstories
  // =====================================================================
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Userstory-Tests" });
    s.addText("Alle Cluster und ihre Test-Userstories", { placeholder: "title" });
    s.addText("14 Cluster, 43 Teil-Userstories laut Testplan. Ohne Zusatz: umgesetzt und im Gesamtlauf grün.", { placeholder: "body" });
    // st: 2 umgesetzt, 1 teilweise, 0 offen
    const clusters = [
      ["R", "Rollen, Eigentum, Kollaboration", [["R1", "Administrator", 2], ["R2", "AdeLe-Manager", 2], ["R3", "Lehrkraft → Assistent", 1], ["R4", "Kollaborator", 2], ["R5", "Parallele Gründe", 1], ["R6", "Eigentum übertragen", 2]]],
      ["E", "Drag&Drop-Editor", [["E1", "Referenzpfad nachbauen", 2], ["E2", "Logik-Kombinationen", 1]]],
      ["C", "Zugangsbedingungen", [["C1", "Vorgänger", 2], ["C3", "Manuelle Freigabe", 2], ["", "C2 Kurszugehörigkeit gestrichen", 3]]],
      ["T", "Zeitabhängige Bedingungen", [["T1", "Startzeit", 2], ["T2", "Zeitfenster", 2], ["T3", "Relative Zeit", 2], ["T4", "Zeit UND Vorgänger", 2], ["T5", "Zeit ODER Bedingung", 2]]],
      ["F", "Feedback und Sprache", [["F1", "i-Symbol", 0], ["F2", "Feedback-Buttons", 0], ["F3", "Kombinierte Conditions", 0]]],
      ["L", "Lernerstand und Prozentwerte", [["L1", "Linearer Pfad", 2], ["L2", "Alternative Lernwege", 2], ["L3", "Manueller Abschluss", 2], ["L4", "Master-Abschluss", 2]]],
      ["O", "Manuelle Abschlüsse, Master-Haken", [["O1", "Manueller Abschluss", 2], ["O2", "Master-Zugang", 2], ["O3", "Master-Abschluss", 2], ["O4", "Beide Master-Schalter", 2]]],
      ["D", "Diagnose / CAT", [["D", "Grenzwert x−ε, x, x+ε gegen die Schwelle einer catquiz-Skala", 2]]],
      ["A", "Alternative Routen", [["A", "Raute: nur T02, nur T03, beide, keine", 2]]],
      ["M", "Laufenden Lernpfad ändern", [["M1", "Restriktion hinzufügen", 2], ["M2", "Restriktion entfernen", 2]]],
      ["H", "Kurseinbindung", [["H1", "Hostkurs-Teilnehmer übernehmen", 2], ["H2", "Teilnehmerquellen", 2], ["H3", "Host-Enrolment-Modi", 2]]],
      ["P", "Lernerdaten in Einbindungen", [["P1", "Eigene Daten", 2], ["P2", "Übersicht aller Teilnehmer", 2], ["P3", "Rollenabhängige Einsicht", 2], ["P4", "Datenschutz-Negativtest", 2]]],
      ["Z", "Gesamtabschluss", [["Z1", "Regulärer Abschluss", 2], ["Z2", "Alternative Route", 2], ["Z3", "Manueller Abschluss", 2], ["Z4", "Master-Abschluss", 2]]],
      ["V", "Zentraler E2E-Vertrag", [["V1", "Berechtigung bis Zugriff", 2], ["V2", "Entzug und Löschen des Pfads", 2]]],
    ];
    const g = 0.18, cw = (CW - 4 * g) / 5, ch = 1.72, y0 = 1.42;
    clusters.forEach(([key, name, st], i) => {
      const x = MX + (i % 5) * (cw + g), y = y0 + Math.floor(i / 5) * (ch + g);
      card(s, x, y, cw, ch);
      s.addShape(pres.shapes.OVAL, { x: x + 0.12, y: y + 0.12, w: 0.4, h: 0.4, fill: { color: TEAL }, line: { color: TEAL, width: 0 } });
      T(s, key, { x: x + 0.12, y: y + 0.12, w: 0.4, h: 0.4, fontSize: 14, bold: true, color: WHITE, align: "center", valign: "middle", fontFace: "Cambria" });
      T(s, name, { x: x + 0.6, y: y + 0.12, w: cw - 0.7, h: 0.4, fontSize: 11, bold: true, valign: "middle" });
      const runs = [];
      st.forEach(([code, text, stt], j) => {
        const last = j === st.length - 1;
        if (stt === 3) { runs.push({ text, options: { italic: true, color: MUTED, breakLine: !last } }); return; }
        runs.push({ text: code + "  ", options: { bold: true, color: stt === 2 ? TEAL : stt === 1 ? ORANGE : MUTED } });
        const suffix = stt === 1 ? " (teilweise)" : stt === 0 ? " (offen)" : "";
        runs.push({ text: text, options: { breakLine: !suffix && !last } });
        if (suffix) runs.push({ text: suffix, options: { bold: true, color: stt === 1 ? ORANGE : MUTED, breakLine: !last } });
      });
      T(s, runs, { x: x + 0.14, y: y + 0.58, w: cw - 0.24, h: ch - 0.64, fontSize: 9.5, paraSpaceAfter: 1.5 });
    });
    // 15. Zelle: Kennzahlen
    {
      const x = MX + 4 * (cw + g), y = y0 + 2 * (ch + g);
      card(s, x, y, cw, ch, { fill: { color: NAVY }, line: { color: NAVY, width: 0 } });
      T(s, [{ text: "37 ", options: { bold: true, fontSize: 24, color: WHITE, fontFace: "Cambria" } }, { text: "umgesetzt", options: { fontSize: 11, color: TINT, breakLine: true } },
            { text: "3 ", options: { bold: true, fontSize: 16, color: WHITE, fontFace: "Cambria" } }, { text: "teilweise: R3, R5, E2", options: { fontSize: 10, color: TINT, breakLine: true } },
            { text: "3 ", options: { bold: true, fontSize: 16, color: WHITE, fontFace: "Cambria" } }, { text: "offen: F1 bis F3", options: { fontSize: 10, color: TINT, breakLine: true } },
            { text: "62 Playwright-Tests in 26 Specs", options: { fontSize: 10, color: WHITE } }],
        { x: x + 0.16, y: y + 0.1, w: cw - 0.3, h: ch - 0.2, valign: "middle", paraSpaceAfter: 2 });
    }
    s.addNotes("Reihenfolge und Benennung nach dem Testplan (enrol_adele/docs/testplan/AdeLe_E2E_Testplan_UserStories.md, §6 bis §18 und §27). C2 wurde am 6.10.2026 gestrichen, weil das Produkt keine solche Zugangsbedingung hat. L2 ist durch Kette A abgedeckt. D läuft nur, wenn das catquiz-Ökosystem installiert ist. Teilweise: R3 ohne den Entzug des Zugriffs, R5 mit drei von sechs Zeilen der Matrix, E2 ohne Klammerungen.");
  }

  // =====================================================================
  // 6 · Stand und Dauer
  // =====================================================================
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Userstory-Tests" });
    s.addText("Aktueller Stand und voraussichtliche Dauer", { placeholder: "title" });
    s.addText("37 von 43 Teil-Userstories sind umgesetzt. Für den Rest sind drei bis fünf Sitzungen zu erwarten.", { placeholder: "body" });

    const cats = ["R", "E", "C", "T", "F", "L", "O", "D", "A", "M", "H", "P", "Z", "V"];
    const done = [4, 1, 2, 5, 0, 4, 4, 1, 1, 2, 3, 4, 4, 2];
    const part = [2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    const open = [0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    const chw = 7.2;
    card(s, MX, 1.5, chw, 4.55, { fill: { color: WHITE } });
    s.addChart(pres.charts.BAR, [
      { name: "umgesetzt", labels: cats, values: done },
      { name: "teilweise", labels: cats, values: part },
      { name: "offen", labels: cats, values: open },
    ], {
      x: MX + 0.1, y: 1.58, w: chw - 0.2, h: 4.4, barDir: "col", barGrouping: "stacked", barGapWidthPct: 45,
      chartColors: [H.accent1, "E8853D", "C4CCD4"],
      showTitle: true, title: "Teil-Userstories je Cluster", titleFontSize: 12, titleColor: H.dk1, titleFontFace: "+mn-lt",
      showLegend: true, legendPos: "b", legendFontSize: 10.5, legendColor: H.dk2, legendFontFace: "+mn-lt",
      catAxisLabelColor: H.dk1, catAxisLabelFontSize: 12, catAxisLabelFontFace: "+mn-lt",
      valAxisLabelColor: H.dk2, valAxisLabelFontSize: 10, valAxisLabelFontFace: "+mn-lt", valAxisMaxVal: 6, valAxisMajorUnit: 1,
      valGridLine: { color: "E3E8EC", size: 0.75 }, catGridLine: { style: "none" },
      altText: "Gestapeltes Säulendiagramm: umgesetzte, teilweise umgesetzte und offene Teil-Userstories je Cluster",
    });

    const rx = MX + chw + 0.35, rw = W - MX - rx;
    T(s, [{ text: "37 von 43", options: { fontSize: 40, bold: true, color: TEAL, fontFace: "Cambria", breakLine: true } },
          { text: "Teil-Userstories umgesetzt (86 %). Im Erstentwurf dieser Präsentation waren es 13 von 44.", options: { fontSize: 11, color: MUTED } }],
      { x: rx, y: 1.45, w: rw, h: 1.25 });
    label(s, "WAS NOCH FEHLT", rx, 2.85, rw);
    const rest = [
      ["F1 bis F3: Feedback und Sprache", "begonnen, Fixtures stehen, Messung der Zeit-Schritte offen", "1–2"],
      ["R3, R5, E2: Entzug, drei Matrixzeilen, Klammerungen", "R3 und R5 brauchen die Entscheidung zur Assistentenrolle", "1"],
      ["Screenreader-Lauf mit NVDA", "Suite ist gebaut, Lauf unter Windows steht aus", "1"],
      ["Nacharbeit aus neuen Befunden", "Fixes erst nach angelegten Issues", "0–1"],
    ];
    rest.forEach(([t, d, n], i) => {
      const y = 3.15 + i * 0.7;
      s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: rx, y, w: rw, h: 0.62, rectRadius: 0.05, fill: { color: i % 2 ? LIGHT : TINT }, line: { color: BORDER, width: 0.5 } });
      T(s, [{ text: t, options: { bold: true, fontSize: 10.5, breakLine: true } }, { text: d, options: { fontSize: 9.5, color: MUTED } }],
        { x: rx + 0.14, y, w: rw - 1.15, h: 0.62, valign: "middle" });
      T(s, n, { x: rx + rw - 0.95, y, w: 0.8, h: 0.62, fontSize: 16, bold: true, color: TEAL, align: "right", valign: "middle", fontFace: "Cambria" });
    });
    T(s, [{ text: "Summe: 3–5 Sitzungen", options: { bold: true, fontSize: 13 } }],
      { x: rx, y: 5.98, w: rw, h: 0.3, align: "right" });

    // Leiste unten
    const by = 6.3;
    card(s, MX, by, CW, 0.6, { fill: { color: NAVY }, line: { color: NAVY, width: 0 } });
    T(s, [{ text: "Letzter Gesamtlauf, Plugin-Stand 2026100708:  ", options: { bold: true, color: WHITE } },
          { text: "62 Userstory-Tests grün (local 43 · mod 13 · enrol 6)  ·  PHPUnit 433  ·  Jest 346  ·  phpcs 0 Fehler, 0 Warnungen", options: { color: TINT } }],
      { x: MX + 0.2, y: by, w: CW - 0.4, h: 0.6, fontSize: 11.5, valign: "middle" });
    s.addNotes("Zählung nach den Abschnitten des Testplans; C2 ist gestrichen, daher 43 statt 44. Teilweise: R3 (Entzug des Editorzugriffs beim Entfernen der Kursrolle ungeprüft), R5 (drei von sechs Zeilen), E2 (Klammerungen). Offen: Kette F, begonnen und unterbrochen; die Beobachtungen aus den Probeläufen sind noch nicht bestätigt. Die Schätzung in Sitzungen ist eine Erwartung, keine Messung. Gesamtlauf: PHPUnit local 354 (1 übersprungen, 1 unvollständig), enrol 57, mod 22; Jest 346 in 73 Suiten.");
  }

  // =====================================================================
  // 7 · Probleme und Fixes
  // =====================================================================
  pres.addSection({ title: "Befunde" });
  {
    const s = pres.addSlide({ masterName: "CONTENT", sectionTitle: "Befunde" });
    s.addText("Was die Testung aufgedeckt hat, und was behoben ist", { placeholder: "title" });
    s.addText("Nach Art geordnet. Nummern ohne Präfix gehören zu local_adele; alle behobenen Punkte sind ausgeliefert.", { placeholder: "body" });
    // [Problem, Fix, Tag, offen?]
    const cols = [
      ["FaUniversalAccess", "Zugänglichkeit für Menschen", "Screenreader, Tastatur, Kontrast", [
        ["Knoten ohne Namen und Zustand", "Name aus Kurs und Zustand, als Gruppe ausgezeichnet", "#575", 0],
        ["Lernpfad nur als Grafik", "Zusätzliche Listenansicht mit Voraussetzungen", "#575", 0],
        ["Kurse nur mit der Maus einfügbar", "Einfügen-Dialog per Tastatur, Entf löscht; Umhängen fehlt noch", "#575", 0],
        ["Freischaltungen blieben stumm", "Ansagen über eine Live-Region", "#575", 0],
        ["Zustandsfarben unter 3:1, Fortschrittsbalken ohne Wert", "Farben abgedunkelt, Wert gebunden", "#575", 0],
        ["Infokarte am Knoten nur per Mausklick", "Festgehalten, noch nicht entschieden", "", 1],
      ]],
      ["FaRobot", "Zugänglichkeit für Technik", "Testbarkeit, Automaten", [
        ["Keine stabilen Kennungen in der Oberfläche", "Testkennungen und Zustand an Knoten, Listen, Editor", "#574", 0],
        ["Icon-Links mit leerem Ziel, Schalter ohne Zustand", "Benannte Schaltflächen mit Zustandsangabe", "#574", 0],
        ["Drag&Drop galt als nicht testbar", "Testtechnik gefunden, Begründung im Issue korrigiert", "#580", 0],
        ["Ablagezonen hängen am zuerst passierten Knoten", "Bedienpunkt bleibt bestehen", "#580", 1],
        ["Zeitabläufe im Test nicht steuerbar", "Moodle-Uhr im Code, Server-Testuhr im Test", "#582", 0],
        ["Kontrastverstoß nur auf dem CI-Runner", "Test meldet jetzt Farben und Verhältnis; Ursache unbelegt", "", 1],
      ]],
      ["FaCogs", "Funktionalität", "Verhalten fehlte oder war anders gewollt", [
        ["Abschlussregel „Lernpfad abgeschlossen“ wirkte nie", "Regel wird ausgewertet, auch bei Rücknahme", "mod #38", 0],
        ["Paralleler Knoten ohne Wirkung auf Nachfolger", "Zählt als Vorgänger", "#584", 0],
        ["Lehrkräfte wurden als Lernende geführt", "Die Rolle entscheidet, wer teilnimmt", "#587", 0],
        ["Teilnehmerliste zeigte Lernende anderer Kurse", "Schnittmenge aus Kurs und Pfad", "#588", 0],
        ["Lehrkraft ohne Bearbeitungsrecht", "Sieht alles, ändert nichts", "mod #39", 0],
        ["Assistentenrolle bleibt nach Entzug der Kursrolle", "Issue-Entwurf liegt vor", "", 1],
      ]],
      ["FaCode", "Codequalität", "Zeit-API, Plattform, Tests, CI", [
        ["Direkte Uhrzugriffe mit time(), date(), strtotime()", "Moodles Uhr-API in allen drei Plugins, Wächtertest", "#582 · enrol #11 · mod #36", 0],
        ["Zeitbedingungen als Zeichenketten, Zeitzone mehrdeutig", "Zeitstempel, halboffenes Intervall, Migration", "#581", 0],
        ["Mindestversion uneinheitlich", "Einheitlich Moodle 4.5", "#583", 0],
        ["Test hing am echten Datum, ab 1.1.2027 rot", "Tests laufen mit eingefrorener Uhr", "", 0],
        ["CI brach nach Umbenennung von Repository und Branch", "Begleit-Plugins vom laufenden Repository", "", 0],
      ]],
      ["FaBug", "Bugs", "Falsches Verhalten, Datenverlust, Datenschutz", [
        ["Editor überschrieb den eingetippten Namen", "Doppeltes Laden beseitigt; stiller Datenverlust behoben", "#585", 0],
        ["Lernerdaten über fremden Kurs lesbar und änderbar", "Rechte an den einbindenden Kurs gebunden", "#586", 0],
        ["Eigene Einschreibungen lösten Kettenreaktion aus", "ADELE-Einschreibungen zählen nicht als Quelle", "mod #37", 0],
        ["Zeiten falsch angezeigt (UTC, Millisekunden)", "Anzeige aus Zeitstempeln in Ortszeit", "#581", 0],
        ["„Zugang bis …“ fiel nach dem #581-Umbau weg", "Regression im Test gefunden und behoben", "#581", 0],
        ["Feedbacktexte: Sprachmix, unpassende Meldungen", "Kette F, Beobachtung noch zu bestätigen", "", 1],
      ]],
    ];
    const g = 0.16, cw = (CW - 4 * g) / 5, y0 = 1.45, hh = 0.78, ih = 0.775, colH = 5.5;
    for (let c = 0; c < cols.length; c++) {
      const [ic, name, sub, items] = cols[c];
      const x = MX + c * (cw + g);
      card(s, x, y0, cw, colH, { fill: { color: WHITE } });
      s.addShape(pres.shapes.RECTANGLE, { x: x + 0.01, y: y0 + 0.01, w: cw - 0.02, h: hh, fill: { color: LIGHT }, line: { color: LIGHT, width: 0 } });
      await iconDot(s, ic, x + 0.12, y0 + 0.17, 0.44);
      T(s, [{ text: name, options: { bold: true, fontSize: 11.5, breakLine: true } }, { text: sub, options: { fontSize: 8.5, color: MUTED } }],
        { x: x + 0.64, y: y0 + 0.06, w: cw - 0.7, h: hh - 0.1, valign: "middle" });
      items.forEach(([p, f, tag, isOpen], i) => {
        const y = y0 + hh + 0.08 + i * ih;
        s.addShape(pres.shapes.OVAL, { x: x + 0.13, y: y + 0.06, w: 0.11, h: 0.11, fill: { color: isOpen ? ORANGE : GREEN }, line: { color: isOpen ? ORANGE : GREEN, width: 0 } });
        const runs = [{ text: p, options: { bold: true, breakLine: true } }, { text: f + (tag || isOpen ? "  " : ""), options: { color: MUTED } }];
        if (isOpen) runs.push({ text: tag ? "offen · " + tag : "offen", options: { bold: true, color: ORANGE } });
        else if (tag) runs.push({ text: tag, options: { bold: true, color: TEAL } });
        T(s, runs, { x: x + 0.32, y, w: cw - 0.42, h: ih - 0.04, fontSize: 9 });
        if (i < items.length - 1) s.addShape(pres.shapes.LINE, { x: x + 0.12, y: y + ih - 0.045, w: cw - 0.24, h: 0, line: { color: BORDER, width: 0.5 } });
      });
    }
    // Legende
    s.addShape(pres.shapes.OVAL, { x: W - MX - 4.3, y: 7.14, w: 0.11, h: 0.11, fill: { color: GREEN }, line: { color: GREEN, width: 0 } });
    T(s, "behoben und ausgeliefert", { x: W - MX - 4.13, y: 7.08, w: 1.6, h: 0.25, fontSize: 9, color: MUTED, valign: "middle" });
    s.addShape(pres.shapes.OVAL, { x: W - MX - 2.45, y: 7.14, w: 0.11, h: 0.11, fill: { color: ORANGE }, line: { color: ORANGE, width: 0 } });
    T(s, "offen", { x: W - MX - 2.28, y: 7.08, w: 0.6, h: 0.25, fontSize: 9, color: MUTED, valign: "middle" });
    s.addNotes("Aus der Testphase stammen 14 Issues: local_adele #580 bis #588, enrol_adele #11, mod_adele #36 bis #39. Umgesetzt und ausgeliefert sind alle bis auf den Bedienpunkt der Ablagezonen in #580. #574 (Testkennungen) und #575 (Barrierefreiheit) waren die Voraussetzung der Suite. #586: drei Umgehungen lesend und eine schreibend, gemessen in Kette P. #585: Ursache belegt durch künstlich verzögerte zweite Antwort. Offen und nicht belegt: Ursache des Kontrastverstoßes auf dem CI-Runner; Beobachtungen der Kette F (gemischte Sprache nach Kursabschluss, Meldung „manual completion recorded“ ohne Abschluss, „Missing date to …“). Der Wegfall von „Zugang bis …“ war eine Regression aus dem eigenen #581-Umbau.");
  }

  await pres.writeFile({ fileName: OUT });
  await applyTheme(OUT, THEME);
  console.log("written", OUT);
})().catch((e) => { console.error(e); process.exit(1); });
