import os, re, json, subprocess, sys
src, out = sys.argv[1], sys.argv[2]
mods = []
for f in sorted(x for x in os.listdir(src) if re.match(r'\d\d-', x)):
    t = open(os.path.join(src, f)).read()
    fm, body = re.match(r'---\n(.*?)\n---\n(.*)', t, re.S).groups()
    meta = dict(re.findall(r'^(\w+): (.*)$', fm, re.M))
    title = meta['titulo'].strip('"')
    body = re.sub(r'^# .*\n', '', body, count=1)
    main, quiz = body.split('\n## Quiz', 1)
    html = subprocess.run(['pandoc', '-f', 'gfm', '-t', 'html', '--wrap=none'], input=main, capture_output=True, text=True).stdout
    qs = []
    for block in re.split(r'\n(?=\d+\. )', quiz.strip()):
        m = re.match(r'\d+\. (.*?)\n(.*)', block, re.S)
        if not m: continue
        answers = [{'label': a.strip(), 'correct': c == 'x'} for c, a in re.findall(r'- \[( |x)\] (.*)', m.group(2))]
        qs.append({'title': m.group(1).strip(), 'answers': answers})
    mods.append({'n': int(meta['modulo']), 'slug': f[:-3], 'title': title, 'minutes': int(meta['duracao_min']), 'publico': meta['publico'], 'html': html, 'md': main.strip(), 'questions': qs})
json.dump(mods, open(out, 'w'), ensure_ascii=False, indent=1)
print([(m['n'], m['title'], len(m['questions'])) for m in mods])
