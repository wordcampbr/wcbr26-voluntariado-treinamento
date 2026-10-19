# Treinamento de Voluntários — WordCamp Brasil 2026

Curso para a equipe de voluntários do [WordCamp Brasil 2026](https://brasil.wordcamp.org/2026/) (30 e 31 de outubro, Universidade FUMEC, Belo Horizonte), feito com o [Sensei LMS](https://wordpress.org/plugins/sensei-lms/).

**[▶ Abrir o treinamento no WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/wordcampbr/wcbr26-voluntariado-treinamento/main/playground/blueprint.json)**

São 9 lições curtas (cerca de 50 minutos no total), cada uma com um quiz de 3 perguntas, e uma avaliação final de 10 perguntas. Quem passa na avaliação final recebe o certificado de conclusão.

O Playground roda no seu navegador: ao fechar ou recarregar a aba, o progresso e o certificado se perdem. Faça o curso de uma vez e baixe o certificado no fim.

O resumo prático para os dias do evento fica no **[Guia do Voluntariado](https://brasil.wordcamp.org/2026/guia-voluntariado/)** (protegido por senha; a senha é passada no grupo do voluntariado).

## Lições

| # | Lição | Público |
|---|---|---|
| 1 | Boas-vindas: o que é um WordCamp e o papel de quem é voluntário | todos |
| 2 | Logística: escala, chegada, identificação e fim do turno | todos |
| 3 | Código de Conduta na prática | todos |
| 4 | Recebendo um relato ou incidente, passo a passo | todos |
| 5 | Credenciamento, recepção e informações | por função |
| 6 | Salas e palestrantes: apoio de sala, tempo e microfone | por função |
| 7 | Intervalos, alimentação, patrocinadores, foto e vídeo, coringa | por função |
| 8 | Acessibilidade, acolhimento, alergias e segurança | todos |
| 9 | Dia da Colaboração e depois do evento | opcional |
| 10 | Avaliação final | todos |

## Estrutura

- `modulos/`: o texto de cada lição em Markdown, com o quiz no fim (`[x]` marca a resposta certa). É a fonte de tudo; veja `modulos/README.md`.
- `sensei/modulos_json.py`: converte os módulos em `modulos.json`.
- `sensei/import-curso.php`: cria o curso, as lições e os quizzes no Sensei a partir do `modulos.json`.
- `sensei/mu-plugins/wcbr-nome-certificado.php`: pede o nome completo para o certificado.
- `sensei/identidade/`: aplica a identidade visual do site do evento (estilos globais, fontes, logo, cabeçalho, rodapé e página inicial).
- `playground/blueprint.json`: monta o site inteiro no Playground, lendo os arquivos deste repositório.

## Atualizar o curso

Depois de editar um módulo, gere o `modulos.json` de novo e faça push; o Playground lê sempre a versão da branch `main`.

```bash
python3 -I sensei/modulos_json.py modulos playground/modulos.json
```

### Num site local (WordPress Studio)

```bash
python3 -I sensei/modulos_json.py modulos <site>/modulos.json
cp sensei/import-curso.php <site>/
cd <site> && studio wp eval-file import-curso.php recriar

cp -R sensei/identidade/uploads-wcbr <site>/wp-content/uploads/wcbr
cp sensei/identidade/aplicar-identidade.php sensei/identidade/identidade.json <site>/
cd <site> && studio wp eval-file aplicar-identidade.php
```

`recriar` apaga o curso existente e importa de novo.
