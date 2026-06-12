<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autogate Grobogan</title>

    <link rel="stylesheet" href="https://cdn.simplecss.org/simple.css">
    <style>
        body {
            color: var(--text);
            background-color: var(--bg);
            font-size: 1.15rem;
            line-height: 1.5;
            display: unset;
        }

        .full-width {
            width: 100%;
        }

        .parameter-table thead {
            text-transform: capitalize;
        }

        .green {
            color: rgb(166, 205, 166);
        }

        pre {
            white-space: pre-wrap !important;
            overflow: unset;
            font-family: 'Courier New', Courier, monospace;
        }

        main {
            padding: 100px 150px;
        }

        .labelBox {
            stroke: #ccccff;
            fill: #ececff;
        }

         .actor-box {
            stroke: red !important;
        }
    </style>

</head>

<body>

    <header>
        <h1>Autogate Grobogan</h1>

    </header>
    <main>
        {!!$content!!}
    </main>
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';


        // select <pre class="mermaid"> _and_ <pre><code class="language-mermaid">
        document.querySelectorAll("pre.mermaid, pre>code.language-mermaid").forEach($el => {
            // if the second selector got a hit, reference the parent <pre>
            if ($el.tagName === "CODE")
                $el = $el.parentElement
            // put the Mermaid contents in the expected <div class="mermaid">
            // plus keep the original contents in a nice <details>
            $el.outerHTML = `
    <div class="mermaid">${$el.textContent}</div>
    <details>
      <summary>Diagram source</summary>
      <pre>${$el.textContent}</pre>
    </details>
  `
        })


        // initialize Mermaid to [1] log errors, [2] have loose security for first-party
        // authored diagrams, and [3] respect a preferred dark color scheme
        mermaid.initialize({
            logLevel: "error", // [1]
            securityLevel: "loose", // [2]
            theme: (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) ?
                "dark" :
                "default" // [3]
        })
    </script>
    <footer>
        <p>Autogate Grobogan &copy; 2025</p>
    </footer>
</body>

</html>