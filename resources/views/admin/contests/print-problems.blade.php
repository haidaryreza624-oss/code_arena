<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $contest->name }} · Contest problems</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #172033;
            background: #e9edf3;
            font-family: Arial, sans-serif;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            color: #fff;
            background: #172033;
        }
        .toolbar button {
            border: 0;
            border-radius: 5px;
            padding: 9px 16px;
            color: #fff;
            background: #0d6efd;
            font: inherit;
            cursor: pointer;
        }
        #pages { padding: 18px 0; }
        .sheet {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 210mm;
            height: 297mm;
            margin: 0 auto 18px;
            padding: 16mm 17mm 13mm;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 12px #1720332b;
            break-after: page;
            page-break-after: always;
        }
        .sheet:last-child { break-after: auto; page-break-after: auto; }
        .page-content {
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        .page-footer {
            display: flex;
            justify-content: space-between;
            padding-top: 3mm;
            border-top: 1px solid #d5dbe5;
            color: #667085;
            font-size: 8pt;
        }
        .cover {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .cover-kicker { color: #0d6efd; font-size: 11pt; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .cover h1 { margin: 12mm 0 3mm; font-size: 30pt; line-height: 1.12; }
        .cover-slug { color: #667085; font-size: 11pt; }
        .cover-rule { width: 28mm; height: 2mm; margin: 9mm 0; background: #0d6efd; }
        .cover h2 { margin: 0 0 5mm; font-size: 15pt; }
        .team-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 2mm 6mm;
            margin: 0;
            padding: 0;
            list-style: none;
            font-size: 9pt;
        }
        .team-list li { padding: 1.5mm 0; border-bottom: 1px solid #e6e9ef; overflow-wrap: anywhere; }
        .team-copy { display: block; font-size: 18pt; font-weight: 700; }
        .team-copy li { border: 0; }
        .cover-date { margin-top: auto; padding-top: 8mm; color: #667085; font-size: 9pt; }
        .sheet-heading {
            margin: 0 0 6mm;
            padding-bottom: 3mm;
            border-bottom: 2px solid #0d6efd;
            font-size: 19pt;
        }
        .toc-row {
            display: grid;
            grid-template-columns: 15mm 1fr 16mm;
            gap: 4mm;
            align-items: baseline;
            padding: 3mm 0;
            border-bottom: 1px solid #dfe4ec;
            color: inherit;
            text-decoration: none;
            font-size: 10pt;
        }
        .toc-label { font-weight: 700; }
        .toc-title { overflow-wrap: anywhere; }
        .toc-page { text-align: right; font-variant-numeric: tabular-nums; }
        .problem-heading { margin: 0 0 2mm; font-size: 22pt; }
        .problem-meta { margin: 0 0 7mm; color: #667085; font-size: 9pt; }
        .section-heading { margin: 5mm 0 2mm; font-size: 12pt; }
        .body-line {
            margin: 0;
            color: #202939;
            font: 9pt/1.35 "Courier New", monospace;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }
        .sample-title { margin: 4mm 0 2mm; font-size: 11pt; }
        .io-label { margin: 2mm 0 1mm; color: #475467; font-size: 8pt; font-weight: 700; text-transform: uppercase; }
        .empty { color: #667085; font-size: 10pt; }
        @media print {
            @page { size: A4; margin: 0; }
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            #pages { padding: 0; }
            .sheet { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <span>{{ $contest->name }} — problem booklet</span>
    <button type="button" onclick="window.print()">Print / save PDF</button>
</div>
<main id="pages" aria-live="polite">
    <div class="sheet"><div class="page-content"><p class="empty">Preparing the contest booklet…</p></div></div>
</main>
<script id="booklet-data" type="application/json">{!! json_encode([
    'contest' => $contest->name,
    'slug' => $contest->slug,
    'teams' => $teams,
    'problems' => $problems,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>
<script>
(() => {
    const data = JSON.parse(document.getElementById('booklet-data').textContent);
    const pages = document.getElementById('pages');
    let pageNumber = 0;

    function makeSheet(title, anchor) {
        const sheet = document.createElement('section');
        sheet.className = 'sheet';
        sheet.dataset.pageNumber = String(++pageNumber);
        if (anchor) sheet.id = anchor;

        const content = document.createElement('div');
        content.className = 'page-content';
        if (title) {
            const heading = document.createElement('h2');
            heading.className = 'sheet-heading';
            heading.textContent = title;
            content.appendChild(heading);
        }

        const footer = document.createElement('footer');
        footer.className = 'page-footer';
        const contestName = document.createElement('span');
        contestName.textContent = data.contest;
        const page = document.createElement('span');
        page.textContent = String(pageNumber);
        footer.append(contestName, page);
        sheet.append(content, footer);
        pages.appendChild(sheet);

        return { sheet, content };
    }

    pages.replaceChildren();

    function addCover(teamName) {
        const cover = makeSheet();
        const coverContent = document.createElement('div');
        coverContent.className = 'cover';
        const kicker = document.createElement('div');
        kicker.className = 'cover-kicker';
        kicker.textContent = 'Contest problem booklet';
        const contestTitle = document.createElement('h1');
        contestTitle.textContent = data.contest;
        const slug = document.createElement('div');
        slug.className = 'cover-slug';
        slug.textContent = data.slug;
        const rule = document.createElement('div');
        rule.className = 'cover-rule';
        const teamTitle = document.createElement('h2');
        teamTitle.textContent = 'Prepared for';
        const teamList = document.createElement('ul');
        teamList.className = 'team-list team-copy';
        const team = document.createElement('li');
        team.textContent = teamName;
        teamList.appendChild(team);
        const date = document.createElement('p');
        date.className = 'cover-date';
        date.textContent = 'Contest problems and sample test cases';
        coverContent.append(kicker, contestTitle, slug, rule, teamTitle, teamList, date);
        cover.content.appendChild(coverContent);
    }

    function addContents(teamName, copyIndex) {
        let toc = makeSheet('Table of contents — '+teamName);
        const rows = [];
        data.problems.forEach((problem, index) => {
            const row = document.createElement('a');
            row.className = 'toc-row';
            row.href = '#problem-'+copyIndex+'-'+index;
            const label = document.createElement('span');
            label.className = 'toc-label';
            label.textContent = problem.label;
            const title = document.createElement('span');
            title.className = 'toc-title';
            title.textContent = problem.title;
            const number = document.createElement('span');
            number.className = 'toc-page';
            number.textContent = '—';
            row.append(label, title, number);
            toc.content.appendChild(row);
            if (toc.content.scrollHeight > toc.content.clientHeight && toc.content.childElementCount > 1) {
                toc.content.removeChild(row);
                toc = makeSheet('Table of contents — '+teamName+' (continued)');
                toc.content.appendChild(row);
            }
            rows.push(number);
        });
        return rows;
    }

    function appendBlock(state, block) {
        state.content.appendChild(block);
        if (state.content.scrollHeight > state.content.clientHeight) {
            state.content.removeChild(block);
            const next = makeSheet(state.continuationTitle);
            next.continuationTitle = state.continuationTitle;
            next.content.appendChild(block);
            return next;
        }
        return state;
    }

    function textChunks(value) {
        const chunks = [];
        String(value ?? '').split(/\r?\n/).forEach((line) => {
            if (line.length === 0) {
                chunks.push('');
                return;
            }
            for (let offset = 0; offset < line.length; offset += 70) {
                chunks.push(line.slice(offset, offset + 70));
            }
        });
        return chunks;
    }

    function appendTextBlocks(state, value) {
        for (const chunk of textChunks(value)) {
            const block = document.createElement('pre');
            block.className = 'body-line';
            block.textContent = chunk || ' ';
            state = appendBlock(state, block);
        }
        return state;
    }

    function addProblem(problem, copyIndex, problemIndex, tocRow) {
        const anchor = 'problem-'+copyIndex+'-'+problemIndex;
        const continuationTitle = problem.label+' · '+problem.title+' (continued)';
        let state = makeSheet('', anchor);
        state.continuationTitle = continuationTitle;

        const heading = document.createElement('h1');
        heading.className = 'problem-heading';
        heading.textContent = problem.label+' · '+problem.title;
        state = appendBlock(state, heading);

        const meta = document.createElement('p');
        meta.className = 'problem-meta';
        meta.textContent = problem.code+' · '+problem.points+' points';
        state = appendBlock(state, meta);

        const statementTitle = document.createElement('h2');
        statementTitle.className = 'section-heading';
        statementTitle.textContent = 'Problem statement';
        state = appendBlock(state, statementTitle);
        if (!String(problem.statement ?? '').trim()) {
            const empty = document.createElement('p');
            empty.className = 'empty';
            empty.textContent = 'No statement provided.';
            state = appendBlock(state, empty);
        } else {
            state = appendTextBlocks(state, problem.statement);
        }

        const casesTitle = document.createElement('h2');
        casesTitle.className = 'section-heading';
        casesTitle.textContent = 'Sample test cases';
        state = appendBlock(state, casesTitle);

        if (problem.sample_cases.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'empty';
            empty.textContent = 'No sample test cases.';
            state = appendBlock(state, empty);
        } else {
            problem.sample_cases.forEach((testCase) => {
                const caseTitle = document.createElement('h3');
                caseTitle.className = 'sample-title';
                caseTitle.textContent = 'Sample '+testCase.ordinal;
                state = appendBlock(state, caseTitle);

                const inputLabel = document.createElement('div');
                inputLabel.className = 'io-label';
                inputLabel.textContent = 'Input';
                state = appendBlock(state, inputLabel);
                state = appendTextBlocks(state, testCase.input);

                const outputLabel = document.createElement('div');
                outputLabel.className = 'io-label';
                outputLabel.textContent = 'Expected output';
                state = appendBlock(state, outputLabel);
                state = appendTextBlocks(state, testCase.output);
            });
        }

        tocRow.textContent = document.getElementById(anchor).dataset.pageNumber;
    }

    const participants = data.teams.length ? data.teams : ['No participating team assigned'];
    participants.forEach((teamName, copyIndex) => {
        addCover(teamName);
        const tocRows = addContents(teamName, copyIndex);
        data.problems.forEach((problem, problemIndex) => {
            addProblem(problem, copyIndex, problemIndex, tocRows[problemIndex]);
        });
    });

    document.title = data.contest+' · Problem booklet';
    window.addEventListener('load', () => window.print(), { once: true });
})();
</script>
</body>
</html>
