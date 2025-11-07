// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A way to call HTML fragments to be inserted as required via JavaScript.
 *
 * @module     core/fragment
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */
define(['jquery', 'core/ajax'], function($, ajax) {

    /**
     * Loads an HTML fragment through a callback.
     *
     * @method loadFragment
     * @param {string} component Component where callback is located.
     * @param {string} callback Callback function name.
     * @param {integer} contextid Context ID of the fragment.
     * @param {object} params Parameters for the callback.
     * @return {Promise} JQuery promise object resolved when the fragment has been loaded.
     */
    var loadFragment = function(component, callback, contextid, params) {
        // Change params into required webservice format.
        var formattedparams = [];
        for (var index in params) {
            formattedparams.push({
                name: index,
                value: params[index]
            });
        }

        return ajax.call([{
            methodname: 'core_get_fragment',
            args: {
                component: component,
                callback: callback,
                contextid: contextid,
                args: formattedparams
            }
        }])[0];
    };

    /**
     * Converts the JS that was received from collecting JS requirements on the $PAGE so it can be added to the existing page
     *
     * @param {string} js
     * @return {string}
     */
    var processCollectedJavascript = function(js) {
        var jsNodes = $(js);
        var allScript = '';
        jsNodes.each(function(index, scriptNode) {
            scriptNode = $(scriptNode);
            var tagName = scriptNode.prop('tagName');
            if (tagName && (tagName.toLowerCase() == 'script')) {
                if (scriptNode.attr('src')) {
                    // We only reload the script if it was not loaded already.
                    var exists = false;
                    $('script').each(function(index, s) {
                        if ($(s).attr('src') == scriptNode.attr('src')) {
                            exists = true;
                        }
                        return !exists;
                    });
                    if (!exists) {
                        allScript += ' { ';
                        allScript += ' node = document.createElement("script"); ';
                        allScript += ' node.type = "text/javascript"; ';
                        allScript += ' node.src = decodeURI("' + encodeURI(scriptNode.attr('src')) + '"); ';
                        allScript += ' document.getElementsByTagName("head")[0].appendChild(node); ';
                        allScript += ' } ';
                    }
                } else {
                    allScript += ' ' + scriptNode.text();
                }
            }
        });
        return allScript;
    };

    return {
        /**
         * Appends HTML and JavaScript fragments to specified nodes.
         * Callbacks called by this AMD module are responsible for doing the appropriate security checks
         * to access the information that is returned. This only does minimal validation on the context.
         *
         * @method fragmentAppend
         * @param {string} component Component where callback is located.
         * @param {string} callback Callback function name.
         * @param {integer} contextid Context ID of the fragment.
         * @param {object} params Parameters for the callback.
         * @return {Deferred} new promise that is resolved with the html and js.
         */
        loadFragment: function(component, callback, contextid, params) {
            var promise = $.Deferred();
            loadFragment(component, callback, contextid, params).then(function(data) {
                promise.resolve(data.html, processCollectedJavascript(data.javascript));
            }).fail(function(ex) {
                // If server indicates a redirect was attempted during fragment rendering
                // (for example when session expired), Moodle may return an exception
                // with errorcode 'redirecterrordetected'. Handle that here by performing
                // a client-side redirect instead of allowing the generic AJAX error
                // modal to appear.
                try {
                    // ex may be a jQuery XHR or structured object. Check for JSON payload.
                    var redirectUrl = null;
                    if (ex && ex[0] && ex[0].exception) {
                        // Webservice error array format: [{"exception": {"errorcode":"...","message":"...", ...}}]
                        var exception = ex[0].exception;
                        if (exception && exception.errorcode === 'redirecterrordetected') {
                            // If the server provided a redirect URL in the exception message or a 'redirect' field,
                            // prefer that. Otherwise redirect to login page by reloading the response's redirect if present.
                            if (exception.redirect) {
                                redirectUrl = exception.redirect;
                            } else if (exception.message) {
                                // Sometimes the server returns a login url in a javascript payload. Fall back to reload.
                                // As a last resort, reload the current page which will trigger login redirect.
                                redirectUrl = window.location.href;
                            }
                        }
                    }
                    if (redirectUrl) {
                        // Avoid infinite redirect loops by checking a short-lived flag.
                        if (!window.__moodle_fragment_redirecting) {
                            window.__moodle_fragment_redirecting = true;
                            window.location.href = redirectUrl;
                            return; // do not reject, we've redirected.
                        }
                    }
                } catch (e) {
                    // ignore any parsing errors and fall through to default reject
                }
                promise.reject(ex);
            });
            return promise.promise();
        },

        /**
         * Converts the JS that was received from collecting JS requirements on the $PAGE so it can be added to the existing page
         *
         * @param {string} js
         * @return {string}
         */
        processCollectedJavascript: function(js) {
            return processCollectedJavascript(js);
        }
    };
});
