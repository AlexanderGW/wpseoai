=== WPSEO.AI ===
Contributors: alexgw
Tags: ai, seo, wpml, acf, translate, multilingual, chatgpt, openai
Requires at least: 5.2
Tested up to: 6.5.2
Requires PHP: 7.1
Stable tag: 0.0.4
License: MIT
License URI: https://mit-license.org/

WPSEO.AI is a platform that connects your WordPress site, with Artificial Intelligence (AI). Allowing SEO optimizations, such as proofreading, WYSIWYG, Gutenberg block layout improvements, and support for translating your content into different languages, integrating with the [SitePress WPML](http://wpml.org) plugin. Users can purchase credits to use our service, which integrates AI through this WordPress plugin, for the Gutenberg editor.

= Features =

**Content proofreading**
Spelling, punctuation, and grammar facilities.

**Markup and Gutenberg block optimisations**
Provide improvements to your content, and Gutenberg block layouts.

**Multilingual translation**
Integration with WordPress Multilingual ([WPML](http://wpml.org)) plugin.

**Advanced Custom Fields (ACF)**
Support extra, custom defined metadata.

**Summarisations**
Provide context on what has been changed, and why.

**Version control**
Supports the WordPress revisioning system, creates a new draft, for each optimisation.

= Links =
* [Website](https://wpseo.ai/?utm_source=wordpress)
* [Buy credits](https://wpseo.ai/subscription-top-up-credits.html)
* [FAQ](https://wpseo.ai/faq.html?utm_source=wordpress)
* [Terms of Service](https://wpseo.ai/terms-of-service.html)
* [Privacy policy](https://wpseo.ai/privacy-policy.html)

== Frequently Asked Questions ==

= How does the credit system work? =
Users purchase credits and receive a unique Subscription ID and Secret. This information must then be added to your WPSEO.AI WordPress plugin (which you will need to install on your website), on the settings page.

* The Settings page, can be found at following URL path, on your WordPress site:
* /wp-admin/admin.php?page=wpseoai_settings

The cost of each content optimization run (using the 'Finnese' buttons in the WordPress plugin sidebar) is determined by the size and complexity of the data provided.

Refund conditions, can be found on our Terms of Service

During the beta phase, credits can be bought in small amounts. In future, more options will be added to allow the purchase of higher amounts of credits, at better rates.

= Do credits expire? =
No. Your credits will never expire, and are non-transferable.

= Does it integrate with the Gutenberg editor? =
Yes, we provide a sidebar in the Gutenberg editor, with buttons to action submissions, and submission status information.

= How many credits are used on each submission? =
This all depends on how much content is sent to our optimisation services.

For example, the content on this page, including all of the Gutenberg block information, and other metadata, would cost between $0.02 to $0.03 USD worth of credits (two to three cents).

= How do I use the service? =
After purchasing credits and configuring the WordPress plugin with your **Subscription ID** and **Secret**. You can then submit your content through the plugin's sidebar, within the Gutenberg editor. The optimised submission will be sent back to your WordPress site by our services, and saved as a new draft revision, on the original content. You can then decide to keep it, modifying it, or optimise further.

The AI optimised submission response may not be perfect on the first try, so you may want to submit the content, make some changes to what you received, and try optimizing again, it's up to you!

= Does it work with Advanced Custom Fields plugin? =
Yes, we have added support for field types; text, textarea, flexible content, repeater, WYSIWYG, and associated sub-fields.

We're constantly working to improve the features of this plugin, contact us below, if you have a feature idea/request.

= Does it work with WordPress Multilingual (WPML) plugin? =
Yes, you can find a 'Translate (WPML)' panel on the WPSEO.AI Gutenberg plugin sidebar. All enabled languages will be displayed as individual translation buttons.

This functionality is in active development. We will be releasing more features in future versions of the WPSEO.AI plugin.

= Can I use the service with a site behind a proxy? =
Yes. If for any reason there are ever any issues when receiving your submissions, there is a 'retrieve' option available on the dashboard, for each individual submission, and is free to use.

= What is the current development status of the service? =
The service is currently in its beta phase. We are actively working on improving the service functionality, and adding new features during this period, we are providing extra credits to early users! Your credits never expire. You may use them, whenever you wish.

= What are the terms of service and privacy policy? =
Usage of the service is subject to our Terms of Service and Privacy Policy, please review this detailed information on our policies and practices.

= Do you collect payment information? =
No, not directly. We leverage Stripe as our payment gateway. If you have any issues with a subscription/credit top-up, please contact us using the form below.

= Do you support crypto payments? =
Not yet, we will update in future on this.

We are considering integration with PayPal, in the future.

= What information do you collect, and why? =
We collect information submitted to the service, such as your website host, IP address, content data and metadata (such as posts, pages), and potentially personally identifiable information like content author names. This information is used to facilitate the optimization process, and delivery of data back to your WordPress site, via the plugin. Please read our Privacy Policy for more details on data handling.

= How do I contact support if I have any issues? =
Use the contact form at the bottom of our Terms of Service page. Please provide as much information as possible on the form, including your Subscription ID (if applicable), to ensure a quick response.

== Source files ==
All source files for this plugin can be found on the [GitHub plugin page](https://github.com/AlexanderGW/wpseoai)

== Changelog ==

= 0.0.1 =
*Release Date: 31st December 2023*
Initial release

= 0.0.2 =
*Release Date: 1st February 2024*
Security hardening

= 0.0.3 =
*Release Date: 16th March 2024*
Updated naming conventions

= 0.0.4 =
*Release Date: 20th April 2024*
Additional security hardening, and type declaration coverage