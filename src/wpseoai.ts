/*
 * WPSEO.AI
 *
 * @package       WPSEOAI
 * @author        WPSEO.AI Ltd
 * License:       MIT
 *
 * NOTE: This may be refactored, at a later date
 */

// Encoded JSON of response data
declare const WPSEOAI_POST_JSON: string;

type WPSEOAIResponseData = {
    message?: string,
    code?: number,
    auditId?: number
}

/**
 * Toggle collapsable cards, on the WPSEO.AI dashboard
 */
const wpseoaiCardToggles = document.querySelectorAll('.toplevel_page_wpseoai_dashboard .card .toggle') as NodeListOf<Element> | null;
if ( wpseoaiCardToggles !== null ) {
    wpseoaiCardToggles.forEach((toggle) => {
        toggle.addEventListener('click', function (e) {
            const state = toggle.getAttribute('aria-pressed') === 'true';
            toggle.setAttribute(
                'aria-pressed',
                state ? 'false': 'true'
            );

            if (state)
                toggle.nextElementSibling?.classList.remove('show');
            else
                toggle.nextElementSibling?.classList.add('show');
        });
    });
}

/**
 *
 */
const wpseoaiRequest = document.querySelector('#wpseoai-request') as Element | null;
if ( wpseoaiRequest !== null ) {
    const p = document.createElement('p');
    p.innerText = 'Processing...';
    wpseoaiRequest.append(p);

    (async () => {
        const controller =
            typeof AbortController === 'undefined' ? undefined : new AbortController();

        const type = wpseoaiRequest.getAttribute('data-type');
        console.log(type);

        const post = wpseoaiRequest.getAttribute('data-post');
        console.log(post);

        const nonce = wpseoaiRequest.getAttribute('data-nonce');
        console.log(nonce);

        const url = `${window.location.origin}/wp-json/wpseoai/v1/${type}?post=${post}`;
        console.log(url);

        const tooLongTimeout = setTimeout(() => {
            const p = document.createElement('p');
            p.innerHTML = 'This is taking longer than it should. You can <a href="">refresh the page</a>, or try again later. If this problem persists, please <a href="https://wpseo.ai/" target="_blank">contact us</a>';
            wpseoaiRequest.append(p);

            controller?.abort();
        }, 6000);

        const response = await fetch(
            url,
            {
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-WP-Nonce": nonce as string
                },
                signal: controller?.signal,
            }
        );
        const data: WPSEOAIResponseData = await response.json();
        console.log(data);

        clearTimeout(tooLongTimeout);

        // console.log(window.location);
        console.log(response);

        const code = data?.code ?? response.status;
        const message = data?.message ?? response.statusText;
        const auditId = data?.auditId ?? 0;

        const dashboardUrl = `${window.location.origin}/wp-admin/admin.php?page=wpseoai_dashboard`;

        // Error
        if (
            response.status > 200
            || ( response.status === 200 && code === 204 )
        ) {
            const p = document.createElement('p');
            p.innerText = `Error: "${message}" (${code})`;
            wpseoaiRequest.append(p);

            const a: HTMLAnchorElement = document.createElement('a');
            a.href = dashboardUrl;
            a.innerText = `Return to dashboard`;
            wpseoaiRequest.append(a);

            // console.error('Request error', response);
        }

        // Success
        else {
            const p = document.createElement('p');
            p.innerText = 'Success, redirecting...';
            wpseoaiRequest.append(p);

            // console.log('Request success', response);

            if (auditId) {
                window.location.href = `${dashboardUrl}&action=audit&post_id=${auditId}`;
            } else {
                window.location.href = dashboardUrl;
            }
        }
    })();
}
