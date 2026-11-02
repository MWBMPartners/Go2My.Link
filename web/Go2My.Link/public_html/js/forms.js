/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * Go2My.Link — Shared Form Feedback Script (#286)
 * ============================================================================
 *
 * What it does, in plain words:
 *   When someone submits a form, this script switches the form's submit
 *   button(s) off and changes the pressed button's label to "Working…". That
 *   gives the person a sign that something is happening, and it stops a second
 *   click from sending the same request twice (two emails, two links, or a
 *   one-time API key that never gets shown).
 *
 * How it is loaded:
 *   web/_includes/footer.php prints the script tag on every page, with
 *   `defer`. The translated label comes from the tag's
 *   data-g2ml-working-label attribute, so no inline script is needed. If the
 *   attribute is missing the buttons are still switched off, but their
 *   label is left alone (this file holds no English text of its own).
 *
 * Opting a form out:
 *   Add data-g2ml-no-busy="true" to a form that handles its own progress
 *   display (the homepage shorten form does, in app.js), or whose submit
 *   does not load a new page and so would leave the button stuck off.
 *
 * Why it listens on the document, in the bubbling phase:
 *   A form's own onsubmit="return confirm(...)" runs first. If the person
 *   presses Cancel, the browser marks the event as cancelled, and this script
 *   then does nothing. The form stays usable.
 *
 * What it cannot do:
 *   - A listener that runs AFTER this one and cancels the submit would leave
 *     the form marked busy. None of the site's own scripts does that.
 *   - If the person presses the browser's Stop button mid-request, the form
 *     stays busy until the page is reloaded.
 *
 * Keep identical: the dashboard serves its own copy of this file from
 * web/Go2My.Link/_admin/public_html/js/forms.js. A unit test fails if the two
 * copies differ, so change both together.
 *
 * @package    Go2My.Link
 * @subpackage JavaScript
 * @version    1.0.0
 * @since      v1.0.0 — #286
 * ============================================================================
 */

(function () {
    'use strict';

    // What this script has changed so far, so pageshow can put it all back.
    var busyForms   = [];
    var busyButtons = [];
    var busyCopies  = [];

    /**
     * Read the translated "Working…" text from the script tag.
     * Returns an empty string when the tag or attribute is missing.
     */
    function getWorkingLabel() {
        var scriptTag = document.querySelector('script[data-g2ml-working-label]');

        if (!scriptTag) {
            return '';
        }

        return scriptTag.getAttribute('data-g2ml-working-label');
    }

    /**
     * Every submit button that belongs to the form, including a button placed
     * outside the form's tags that points at it with the form="id" attribute.
     */
    function getSubmitButtons(form) {
        var buttons = [];
        var index;

        for (index = 0; index < form.elements.length; index++) {
            if (form.elements[index].type === 'submit') {
                buttons.push(form.elements[index]);
            }
        }

        return buttons;
    }

    /**
     * Send a message to screen readers through the page's polite live region.
     */
    function announce(message) {
        var region = document.getElementById('global-status');

        if (region && message !== '') {
            region.textContent = message;
        }
    }

    /**
     * A disabled button is left out of the form data, so a pressed button that
     * has a name would lose its name/value pair. Copy the pair into a hidden
     * field first. No button on the site has a name today; this keeps the next
     * one that does from breaking.
     */
    function copyButtonPairToHiddenField(form, button) {
        var hiddenField;

        if (!button.name) {
            return;
        }

        hiddenField       = document.createElement('input');
        hiddenField.type  = 'hidden';
        hiddenField.name  = button.name;
        hiddenField.value = button.value;
        form.appendChild(hiddenField);
        busyCopies.push(hiddenField);
    }

    /**
     * Switch one button off. When showLabel is true, also swap its content for
     * a small spinner and the "Working…" text. A button with no visible text
     * (an icon-only button) gets the spinner alone, so table rows do not jump.
     */
    function makeButtonBusy(button, label, showLabel) {
        var record = {
            button: button,
            isInput: (button.tagName === 'INPUT'),
            savedValue: '',
            savedNodes: null,
            savedMinWidth: button.style.minWidth,
            changedLabel: false
        };
        var spinner;
        var hadVisibleText;

        if (showLabel) {
            // Keep the button at least as wide as it was, so it does not shrink.
            button.style.minWidth = Math.ceil(button.getBoundingClientRect().width) + 'px';

            if (record.isInput) {
                record.savedValue = button.value;
                button.value      = label;
            } else {
                hadVisibleText    = (button.textContent.trim() !== '');
                record.savedNodes = document.createDocumentFragment();

                while (button.firstChild) {
                    record.savedNodes.appendChild(button.firstChild);
                }

                spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm';
                spinner.setAttribute('aria-hidden', 'true');
                button.appendChild(spinner);

                if (hadVisibleText) {
                    button.appendChild(document.createTextNode(' ' + label));
                }
            }

            record.changedLabel = true;
        }

        // autocomplete="off" stops Firefox keeping the disabled state when
        // the page comes back from history without its back/forward cache,
        // where the pageshow restore below never runs.
        button.setAttribute('autocomplete', 'off');
        button.disabled = true;
        busyButtons.push(record);
    }

    /**
     * Put one button back exactly as it was.
     */
    function restoreButton(record) {
        var button = record.button;

        if (record.changedLabel) {
            if (record.isInput) {
                button.value = record.savedValue;
            } else {
                while (button.firstChild) {
                    button.removeChild(button.firstChild);
                }

                button.appendChild(record.savedNodes);
            }

            button.style.minWidth = record.savedMinWidth;
        }

        button.disabled = false;
    }

    /**
     * Undo everything this script did. Used when the browser brings a page
     * back from its back-forward cache: the page is shown exactly as it was
     * left, with its buttons still switched off, so they must be freed.
     */
    function restoreAll() {
        var index;
        var region = document.getElementById('global-status');

        for (index = 0; index < busyButtons.length; index++) {
            restoreButton(busyButtons[index]);
        }

        for (index = 0; index < busyCopies.length; index++) {
            if (busyCopies[index].parentNode) {
                busyCopies[index].parentNode.removeChild(busyCopies[index]);
            }
        }

        for (index = 0; index < busyForms.length; index++) {
            busyForms[index].removeAttribute('aria-busy');
        }

        if (region && busyForms.length > 0 && region.textContent === getWorkingLabel()) {
            region.textContent = '';
        }

        busyForms   = [];
        busyButtons = [];
        busyCopies  = [];
    }

    /**
     * The submit handler. Runs after the form's own handlers (see the header).
     */
    function handleSubmit(event) {
        var form = event.target;
        var submitter = event.submitter;
        var target;
        var label;
        var buttons;
        var index;
        var labelledButton;

        if (event.defaultPrevented) {
            return;
        }

        if (!form || form.tagName !== 'FORM') {
            return;
        }

        if (form.getAttribute('data-g2ml-no-busy') === 'true') {
            return;
        }

        // A form that opens in another window or tab leaves this page as it
        // is, so the buttons would stay switched off for no reason.
        target = form.getAttribute('target');

        if (target !== null && target !== '' && target !== '_self') {
            return;
        }

        // Already sent once and waiting: refuse a second send.
        if (form.getAttribute('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }

        label   = getWorkingLabel();
        buttons = getSubmitButtons(form);

        // The pressed button gets the new label. Without a known pressed
        // button (an older browser, or Enter pressed in a field), the form's
        // first submit button does.
        labelledButton = submitter;

        if (!labelledButton || labelledButton.type !== 'submit') {
            labelledButton = buttons[0];
        }

        if (submitter && submitter.type === 'submit') {
            copyButtonPairToHiddenField(form, submitter);
        }

        form.setAttribute('aria-busy', 'true');
        busyForms.push(form);

        for (index = 0; index < buttons.length; index++) {
            if (!buttons[index].disabled) {
                makeButtonBusy(buttons[index], label, (buttons[index] === labelledButton && label !== ''));
            }
        }

        announce(label);
    }

    document.addEventListener('submit', handleSubmit, false);

    window.addEventListener('pageshow', function () {
        restoreAll();
    });
}());
