/*
 * WPSEO.AI
 *
 * @package       WPSEOAI
 * @author        WPSEO.AI Ltd
 * License:       MIT
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
// import { PanelBody, TextControl, Button } from '@wordpress/components';
import { PanelBody, Button } from '@wordpress/components';
import * as React from "react";
import {Dispatch, SetStateAction, useEffect, useState} from "react";

// import { registerBlockType } from '@wordpress/blocks';
import {select} from '@wordpress/data';
// import { useEntityProp } from '@wordpress/core-data';
// import {InspectorControls, useBlockProps} from '@wordpress/block-editor';

import { __ } from '@wordpress/i18n';
import { registerBlockCollection } from '@wordpress/blocks';
// import {ToolsPanel} from "@wordpress/components/build-types/tools-panel";

import apiFetch from '@wordpress/api-fetch';

type WPSEOAISidebarProps = {
    isOpen: boolean,
    setIsOpen: Dispatch<SetStateAction<boolean>>
}

type WPSEOAISidebarState = {
    stale?: boolean,
    locales?: Locales | null,
    defaultLocale?: string | null,
    summary?: string | null,
    submissionState?: number,
    auditId?: number,
    postId?: number,
    revisionId?: number,
};

type Locale = {
    [code: string]: string,
    active: string,
    code: string,
    default_locale: string,
    display_name: string,
    encode_url: string,
    english_name: string,
    id: string,
    major: string,
    native_name: string,
    tag: string,
}

type Locales = {
    [key: string]: Locale
}

type ContextResponse = {
    defaultLocale: string,
    locales: Locales | null,
    summary: string | null
}

type OptimizeResponse = {
    auditId: number,
}

type AuditResponse = {
    state: string,
}

type RetrieveResponse = {
    auditId: number,
}

const WPSEOAISidebar: React.FC<WPSEOAISidebarProps> = ({
    isOpen,
    setIsOpen
}) => {
    let interval: NodeJS.Timeout;

    const [data, setData] = useState<WPSEOAISidebarState>({
        stale: false,
        locales: null,
        defaultLocale: null,
        summary: null,
        submissionState: 0,
        auditId: 0,
        postId: 0,
        revisionId: 0,
    });

    const updateData = (
        newData: WPSEOAISidebarState
    ): void => setData(prev => ({
        ...prev,
        ...newData
    }));

    const getSubmissionState = (): number => {
        return data.submissionState ?? 0;
    };

    const handleError = (
        error: Error,
    ) => {
        // createErrorNotice(error?.message, {
        //     // type: 'snackbar',
        // });
        if ( error.name === 'AbortError' )
            console.log( 'Request has been aborted' );
        console.error(error?.message);
        alert(error?.message);
    };

    const resetSubmissionState = (newData?: WPSEOAISidebarState) => {

        // Stop interval
        clearInterval(interval);

        // Submission state to: none
        updateData({ ...newData, submissionState: 0 });
    };

    const retrieveData = () => {
        console.log(`Post-back not received; attempting submission retrieval`);

        const controller =
            typeof AbortController === 'undefined' ? undefined : new AbortController();

        // Gather context of this post
        apiFetch({
            path: `/wpseoai/v1/retrieve?post=${data.postId}`,
            signal: controller?.signal,
        })
            .then((data: any) => {
                const res: RetrieveResponse = data;
                console.log(res);
                // if (res.hasOwnProperty('auditId'))
                updateData({ stale: true, submissionState: 0 });
            })
            .catch(
                ( error: Error ) => {
                    handleError(error);
                    updateData({ submissionState: 0 });
                }
            );
    };

    const handleClick = (
        e: React.MouseEvent
    ): void => {
        e.preventDefault();

        const locale = e.currentTarget.getAttribute('data-locale');
        if (!locale) return;

        // Submission state to: sent
        updateData({ submissionState: 1 });

        const controller =
            typeof AbortController === 'undefined' ? undefined : new AbortController();

        // Initiate post data submission
        apiFetch({
            path: `/wpseoai/v1/optimize?locale=${locale}&post=${data.postId}`,
            signal: controller?.signal,
        })
            .then((data: any) => {
                let nextData: WPSEOAISidebarState = {};

                const res: OptimizeResponse = data;

                // Post ID for the audit record
                if (res?.auditId)
                    nextData.auditId = res.auditId;

                // Submission state to: received
                nextData.submissionState = 2;

                updateData(nextData);

                console.log(`Submission sent`);
            })
            .catch(
                ( error: Error ) => {
                    handleError(error);

                    // Submission state to: none
                    updateData({ submissionState: 0 });
                }
            );
    };

    useEffect(() => {

        // Monitor state change of a submission
        if (data.submissionState === 2) {
            const highTime = 10000;
            const lowTime = 2500;
            let lastTime = Date.now();
            let i = 0;
            let j = 0;

            const intervalCallback = () => {
                let intervalTime = highTime - (j * lowTime);
                if (intervalTime < lowTime)
                    intervalTime = lowTime;

                let nextTime = lastTime + intervalTime;

                const currentTime = Date.now();
                if (currentTime >= nextTime) {
                    // console.log(`currentTime: ${currentTime}`);
                    // console.log(`intervalTime: ${intervalTime}`);
                    // console.log(`lastTime: ${lastTime}`);
                    // console.log(`nextTime: ${nextTime}`);
                    // console.log(`i: ${i}`);
                    // console.log(`j: ${j}`);

                    // Invalid audit ID
                    if (!data.auditId) {
                        console.log(`Invalid audit ID, unable to track progress of submission`);
                        return resetSubmissionState();
                    }

                    const controller =
                        typeof AbortController === 'undefined' ? undefined : new AbortController();

                    // Request state of audit record
                    apiFetch({
                        path: `/wpseoai/v1/audit?post=${data.auditId}&limit=1`,
                        signal: controller?.signal,
                    })
                        .then((data: any) => {
                            const res: AuditResponse = data;

                            // Audit post has received response
                            if (res?.state === '1') {

                                // Suggest a refresh
                                // TODO: Refactor indicator for submission received
                                resetSubmissionState({ stale: true });

                                console.log(`Response received`);
                            }
                        })
                        .catch(
                            ( error: Error ) => {
                                handleError(error);
                                resetSubmissionState();
                            }
                        );

                    lastTime = currentTime;
                    j++;
                }

                i++;

                // Prevent endless polling
                if (i > 12) {
                    resetSubmissionState();
                    retrieveData();
                }
            };

            interval = setInterval(
                intervalCallback,
                lowTime
            );
        }
    }, [data.submissionState]);

    useEffect(() => {

        // Get current post ID
        const postId = select('core/editor').getCurrentPostId();
        updateData({ postId });

        const revisionId = select('core/editor').getCurrentPostLastRevisionId();
        updateData({ revisionId });

        const controller =
            typeof AbortController === 'undefined' ? undefined : new AbortController();

        // Gather context of this post
        apiFetch({
            path: `/wpseoai/v1/context?post=${postId}`,
            signal: controller?.signal,
        })
            .then((data: any) => {
                let nextData: WPSEOAISidebarState = {};

                const res: ContextResponse = data;
                if (res.hasOwnProperty('defaultLocale'))
                    nextData.defaultLocale = res.defaultLocale;
                if (res.hasOwnProperty('locales'))
                    nextData.locales = res.locales;
                if (res.hasOwnProperty('summary'))
                    nextData.summary = res.summary;

                updateData(nextData);
            })
            .catch(
                ( error: Error ) => {
                    handleError(error);
                }
            );
    }, []);

    const statusColor = data.stale
        ? 'green'
        : (data.submissionState === 2
            ? 'blue'
            : (data.submissionState === 1
                ? 'blue'
                : 'grey'
            )
        );

    return (
        <PluginSidebar
            name="wpseoai-sidebar"
            title="WPSEO.AI (Beta)"
            icon="share-alt"
            // isOpen={isOpen}
            // onRequestClose={() => setIsOpen(false)}
        >
            <PanelBody title={__(`Help`)} initialOpen={false}>
                <p>{__(`The WPSEO.AI service is currently in beta phase. More features are in development.`)}</p>
                <p>{__(`Please note, using the buttons and features on this sidebar, will deduct small chunks of credits, based on the content provided, from your subscription balance.`)}</p>
                <ul>
                    <li><a href={`/wp-admin/revision.php?revision=${data.revisionId}&gutenberg=true`}>View revisions</a></li>
                    <li><a href={`/wp-admin/admin.php?page=wpseoai_dashboard`}>View audit logs</a></li>
                    <li><a href={`https://wpseo.ai/faq.html`} target={`_blank`}>Frequently asked questions</a></li>
                </ul>
            </PanelBody>
            <PanelBody title="Summary of changes" className={!data.summary ? 'hidden' : ''}>
                <p>{data.summary ?? <i>N/A</i>}</p>
            </PanelBody>
            <PanelBody title="Status">
                <p className={`wpseoai-status ${statusColor}`}>
                    <div aria-hidden={`true`}></div>
                    <span>
                        {data.stale ? (
                            <a href={``}>{__(`Reload page`)}</a>
                        ) : (
                            data.submissionState === 2 ? (
                                <i>{__(`Processing`)}</i>
                            ) : (
                                data.submissionState === 1 ? (
                                    <i>{__(`Sending`)}</i>
                                ) : (
                                    <i>{__(`Inactive`)}</i>
                                )
                            )
                        )}
                    </span>
                </p>
            </PanelBody>
            <PanelBody title={__(`Tools`)}>
                <p>
                    <Button
                        data-locale={data.defaultLocale}
                        variant="primary"
                        onClick={handleClick}
                        isBusy={getSubmissionState() > 0}
                        aria-disabled={getSubmissionState() > 0}
                    >
                        {__(`Finesse all content`)}
                    </Button>
                </p>
                {/*<p>*/}
                {/*    <Button*/}
                {/*        // data-locale={data.defaultLocale}*/}
                {/*        variant="secondary"*/}
                {/*        // onClick={handleClick}*/}
                {/*        // isBusy={getSubmissionState() > 0}*/}
                {/*        aria-disabled={true}*/}
                {/*    >*/}
                {/*        {__(`Finesse post content`)}*/}
                {/*    </Button>*/}
                {/*</p>*/}
                {/*<p>*/}
                {/*    <Button*/}
                {/*        // data-locale={data.defaultLocale}*/}
                {/*        variant="secondary"*/}
                {/*        // onClick={handleClick}*/}
                {/*        // isBusy={getSubmissionState() > 0}*/}
                {/*        aria-disabled={true}*/}
                {/*    >*/}
                {/*        {__(`Finesse ACF content`)}*/}
                {/*    </Button>*/}
                {/*</p>*/}
            </PanelBody>
            {!data.locales ? null :
                <PanelBody title={__(`Translate (WPML)`)}>
                    <p>Choose the language to translate this content into. Activate more languages, for more options below.</p>
                    <p>
                        {data.locales && Object.entries(data.locales).map(([code, locale]) => {
                            if (locale.code === data.defaultLocale) return;
                            // console.log(locale);
                            return <Button
                                className={`wpseoai-translate`}
                                data-locale={code}
                                variant="secondary"
                                onClick={handleClick}
                                isBusy={getSubmissionState() > 0}
                                aria-disabled={getSubmissionState() > 0}
                            >{locale.display_name}</Button>
                        })}
                    </p>
                </PanelBody>
            }
        </PluginSidebar>
    );
};

// Wrap the component using the withState higher-order component to manage the sidebar state
const WPSEOAISidebarWithState: React.FC<{}> = () => {
    const [isOpen, setIsOpen] = useState<boolean>(false);

    return (
        <>
            <WPSEOAISidebar isOpen={isOpen} setIsOpen={setIsOpen} />
            <PluginSidebarMoreMenuItem
                target="wpseoai-sidebar"
            >
                WPSEO.AI (Beta)
            </PluginSidebarMoreMenuItem>
        </>
    )
}

// Register the plugin and add the sidebar to the Gutenberg editor
registerPlugin('custom-sidebar', {
    render: WPSEOAISidebarWithState,
});

// Register the collection.
registerBlockCollection( 'wpseoai', {
    title: __( 'WPSEO.AI (Beta)' ),
} );





// TODO: Implement prompt generation block
// registerBlockType( 'wpseoai/prompt', {
//     attributes: {},
//     category: 'text',
//     title: __( 'Prompt generation' ),
//     edit: ( { setAttributes, attributes } ) => {
//         const blockProps = useBlockProps();
//         // const postType = useSelect(
//         //     ( select ) => select( 'core/editor' ).getCurrentPostType(),
//         //     []
//         // );
//         const postType = 'aaaa';
//
//         // const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );
//
//         // const metaFieldValue = meta[ 'myguten_meta_block_field' ];
//         // const updateMetaValue = ( newValue ) => {
//         //     setMeta( { ...meta, myguten_meta_block_field: newValue } );
//         // };
//
//         return (
//             <div { ...blockProps }>
//                 <InspectorControls key="setting">
//                     <div id="wpseoai-prompt-controls">
//                         <fieldset>
//                             <legend className="blocks-base-control__label">
//                                 { __( 'Style', 'wpseoai' ) }
//                             </legend>
//                             {/*<ColorPalette // Element Tag for Gutenberg standard colour selector*/}
//                             {/*    onChange={ onChangeBGColor } // onChange event callback*/}
//                             {/*/>*/}
//                         </fieldset>
//                     </div>
//                 </InspectorControls>
//                 <TextControl
//                     label="Meta Block Field"
//                     value={ postType }
//                     onChange={ () => {} }
//                 />
//             </div>
//         );
//     },
//
//     // No information saved to the block.
//     // Data is saved to post meta via the hook.
//     save: () => {
//         return null;
//     },
// } );


/**
 * Toggle collapsable cards, on the WPSEO.AI dashboard
 */
// const wpseoaiTranslateButtons = document.querySelectorAll('#wpseoai-metabox button') as NodeListOf<Element> | null;
// if ( wpseoaiTranslateButtons !== null ) {
//     const metabox = document.querySelector('#wpseoai-metabox') as Element | null;
//     const post = metabox?.getAttribute('data-post') as string;
//     console.log(post);
//
//     const nonce = metabox?.getAttribute('data-nonce') as string;
//     console.log(nonce);
//
//     wpseoaiTranslateButtons.forEach((button) => {
//         button.addEventListener('click', function (e) {
//             e.preventDefault();
//             (async () => {
//                 const locale = button.getAttribute('data-locale');
//                 console.log(locale);
//
//                 const url = `${window.location.origin}/wp-json/wpseoai/v1/optimize?post=${post}&locale=${locale}`;
//                 console.log(url);
//
//                 const response = await fetch(
//                     url,
//                     {
//                         headers: {
//                             Accept: "application/json",
//                             "Content-Type": "application/json",
//                             "X-WP-Nonce": nonce
//                         },
//                     }
//                 );
//                 const data: WPSEOAIResponseData = await response.json();
//                 console.log(data);
//
//                 // console.log(window.location);
//                 console.log(response);
//
//                 // const code = data?.code ?? response.status;
//                 // const message = data?.message ?? response.statusText;
//                 // const auditId = data?.auditId ?? 0;
//             })();
//         });
//     });
// }


