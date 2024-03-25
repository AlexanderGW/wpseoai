# WPSEO.AI
Pay-as-you-go artificial intelligence (AI); Search engine optimisations (SEO), proofreading, content translation, auditing, and more in development. Our service is currently in beta.

Please visit [WPSEO.AI](https://wpseo.ai) for more information on our services.

Visit our [FAQ](https://wpseo.ai/faq.html) to find out how to set up your WPSEO.AI plugin installation.

You can also [top up credits, or set up a new subscription](https://wpseo.ai/subscription-top-up-credits.html). All credits purchased, do not expire, and can be used at any time.

## Useful links
- [Buy credits](https://wpseo.ai/subscription-top-up-credits.html)
- [Frequently Asked Questions (FAQ)](https://wpseo.ai/faq.html)
- [Terms of Service](https://wpseo.ai/terms-of-service.html)
- [Privacy policy](https://wpseo.ai/privacy-policy.html)
- [WPSEO.AI on WordPress.org](https://wordpress.org/plugins/ai-seo-wp/)

## Known bugs
- Intermittent WSOD: On Gutenberg editor, of translated (via WPML) posts - possible bad escape/encoding

## Requirements
- PHP 7.1+
- Wordpress 5.2+

## Development
All source files for the plugin can be found on the [GitHub plugin page](https://github.com/AlexanderGW/wpseoai)

| Source                        | Target                        |
|-------------------------------|-------------------------------|
| `~/src/wpseoai.scss`          | `~/dist/wpseoai.css`          |
| `~/src/wpseoai.ts`            | `~/dist/main.js`              |
| `~/src/wpseoai_gutenberg.tsx` | `~/dist/wpseoai_gutenberg.js` |


### CSS and JS

Use `pnpm build` or `pnpm dev`

NOTE: For local development; post-back facility would require SSH port forwarding to the outside, with a `wpseoai_return_host` override. Alternatively, use the retrieve facility.

`php -S 127.0.0.1:8000`

### Install WP test env
`bash bin/install-wp-tests.sh <DB_NAME> <DB_USER> '<PASSWORD>' <DB_HOST> <WORDPRESS_VERSION>`

### Run E2E tests
You'll need to establish an `.env`, inside the `tests` directory, with test environment Subscription ID, and Secret values. Contact WPSEO.AI via one of the contact forms on the website, if you wish to try the test environment.

`phpunit --debug`
