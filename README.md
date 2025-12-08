# Jupiter Plugin

Jupiter Plugin is an open-sourced, lite version of Jupiter that provides end-to-end swap flow by linking it in your HTML.

Visit our Demo / Playground over at https://plugin.jup.ag

With several templates to get you started, and auto generated code snippets.

## Local Development

If you are running the project locally, you will need to set `NEXT_PUBLIC_IS_PLUGIN_DEV=true` in your `.env` file.

## Static hosting & standalone swap page

You can host a static version of the plugin using the compiled assets in the `public/` directory. This allows you to serve a simple standalone `swap.html` without Node.js.

- Copy the `public/` folder to your static host (S3, Netlify, or plain webserver).
- Optionally, use `swap.php` on a PHP capable host to inject server-side config.
- If you need server-side proxying for the Jupiter APIs, use `api-proxy.php` in the `public/` folder and configure server environment variable `PROXY_API_KEY` if you need server-side credentials.

Documentation for the `public/` files and usage examples are included in `public/README_SWAP.md`.
