<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />

    <meta http-equiv="Content-Security-Policy"
        content="default-src *;img-src * data:; style-src * 'unsafe-inline'; script-src * 'unsafe-inline' 'unsafe-eval'">

    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="SwaggerUI" />
    <title>PMO Service</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui@5.30.2/dist/swagger-ui.css" />

    <link rel="stylesheet" href="css/swagger-ui.css" />

</head>

<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.30.2/swagger-ui-bundle.js" crossorigin></script>
    <script src="https://unpkg.com/swagger-ui@5.30.2/dist/swagger-ui-standalone-preset.js" crossorigin></script>

    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '../api/v1/docs',
                dom_id: '#swagger-ui',
                docExpansion: "none",
                persistAuthorization: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                layout: "StandaloneLayout",
            });


        };
    </script>
</body>

</html>