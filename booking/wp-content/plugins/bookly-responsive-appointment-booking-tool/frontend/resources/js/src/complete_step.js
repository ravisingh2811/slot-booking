import $ from 'jquery';
import {laddaStart, opt, scrollTo, booklyAjax} from './shared.js';
import stepService from "./service_step";

/**
 * Complete step.
 */
export default function stepComplete(params) {
    var data = $.extend({
            action    : 'bookly_render_complete',
            csrf_token: BooklyL10n.csrf_token,
        }, params),
        $container = opt[params.form_id].$container;
    booklyAjax({
        data: data,
        success: function (response) {
            if (response.success) {
                if (response.final_step_url && !data.error) {
                    document.location.href = response.final_step_url;
                } else {
                    $container.html(response.html);
                    scrollTo($container);
                    $('.bookly-js-start-over', $container).on('click', function (e) {
                        e.preventDefault();
                        laddaStart(this);
                        stepService({form_id: params.form_id, reset_form:true, new_chain: true});
                    });
                }
            }
        }
    });
}