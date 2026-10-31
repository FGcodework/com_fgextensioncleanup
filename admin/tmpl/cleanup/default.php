<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \FGcodework\Component\FgExtensionCleanup\Administrator\View\Cleanup\HtmlView $this */

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t = static fn (string $key): string => Text::_('COM_FGEXTENSIONCLEANUP_' . $key);

$baseUrl = Route::_('index.php?option=com_fgextensioncleanup', false);
$counts = $this->counts;
$typeCounts = $this->typeCounts;
$total = count($this->rows);

$statusTiles = [
    'all' => ['label' => $t('F_ALL'), 'variant' => 'accent', 'count' => $total],
    'thirdparty' => ['label' => $t('F_THIRDPARTY'), 'variant' => 'accent', 'count' => $total - (int) $counts['core']],
    'ok' => ['label' => $t('R_OK_L'), 'variant' => 'ok', 'count' => (int) $counts['ok']],
    'repairable' => ['label' => $t('F_REPAIRABLE'), 'variant' => 'warn', 'count' => (int) $counts['repairable']],
    'ambiguous' => ['label' => $t('F_AMBIGUOUS'), 'variant' => 'accent', 'count' => (int) $counts['ambiguous']],
    'orphan' => ['label' => $t('F_ORPHAN'), 'variant' => 'bad', 'count' => (int) $counts['orphan']],
    'other' => ['label' => $t('F_OTHER'), 'variant' => 'muted', 'count' => (int) $counts['other']],
    'core' => ['label' => $t('F_CORE'), 'variant' => 'muted', 'count' => (int) $counts['core'],
        'sub' => (int) $counts['core_findings'] > 0 ? Text::sprintf('COM_FGEXTENSIONCLEANUP_CORE_FINDINGS', (int) $counts['core_findings']) : ''],
];
$typeLabels = [
    'plugin' => $t('T_PLUGIN'),
    'component' => $t('T_COMPONENT'),
    'module' => $t('T_MODULE'),
    'library' => $t('T_LIBRARY'),
    'package' => $t('T_PACKAGE'),
    'template' => $t('T_TEMPLATE'),
    'language' => $t('T_LANGUAGE'),
    'file' => $t('T_FILE'),
];

// "FG" in the page title is highlighted like the brand on fgcodework.github.io.
$titleHtml = preg_replace('/^FG\b/', '<span>FG</span>', $e(Text::_('COM_FGEXTENSIONCLEANUP')), 1);

// Styles and script go through the Web Asset Manager so they carry the CSP nonce when one is enabled.
Text::script('COM_FGEXTENSIONCLEANUP_VISIBLE_COUNT');
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('core');
$wa->addInlineStyle(<<<'CSS'
.fg-cleanup {
    --bg: #f4f7f9; --bg-soft: #e9eef4;
    --panel: rgba(255,255,255,.86); --panel-solid: #fff;
    --border: rgba(15,25,35,.10); --border-hover: rgba(15,25,35,.20);
    --text: #15202b; --muted: #5f6f7d;
    --accent: #1677d2; --accent-strong: #0d64b8; --accent-soft: rgba(22,119,210,.09); --on-accent: #fff;
    --ok: #1a8f5a; --warn: #b7791f; --bad: #d64545;
    --shadow: 0 20px 60px rgba(28,55,80,.10);
    --radius: 18px;
}
html[data-bs-theme="dark"] .fg-cleanup, [data-bs-theme="dark"] .fg-cleanup {
    --bg: #0b0f14; --bg-soft: #10161e;
    --panel: rgba(18,25,34,.82); --panel-solid: #121922;
    --border: rgba(255,255,255,.09); --border-hover: rgba(255,255,255,.18);
    --text: #f2f5f7; --muted: #9ca8b5;
    --accent: #4da3ff; --accent-strong: #2f8ff5; --accent-soft: rgba(77,163,255,.11); --on-accent: #07111c;
    --ok: #3ecf8e; --warn: #f0b44c; --bad: #ff6b6b;
    --shadow: 0 20px 60px rgba(0,0,0,.28);
}
.fg-cleanup, .fg-cleanup * { box-sizing: border-box; }
.fg-cleanup {
    margin: 0 0 1.5rem;
    padding: clamp(16px, 3vw, 32px);
    border: 1px solid var(--border);
    border-radius: 22px;
    color: var(--text);
    font: 15px/1.6 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background:
        radial-gradient(circle at 76% 0%, color-mix(in srgb, var(--accent) 12%, transparent), transparent 32rem),
        radial-gradient(circle at 0% 32%, color-mix(in srgb, var(--accent) 7%, transparent), transparent 30rem),
        var(--bg);
}
.fg-cleanup a { color: inherit; text-decoration: none; }
.fg-cleanup code { color: var(--accent); background: transparent; padding: 0; font-size: .85em; }

.fg-hero { margin-bottom: 22px; }
.fg-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 5px 11px;
    border: 1px solid color-mix(in srgb, var(--accent) 28%, transparent);
    border-radius: 999px; background: var(--accent-soft); color: var(--accent);
    font-size: .76rem; font-weight: 750; letter-spacing: .05em; text-transform: uppercase;
}
.fg-eyebrow::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor; box-shadow: 0 0 14px currentColor; }
.fg-cleanup h1 { margin: 14px 0 6px; font-size: clamp(1.9rem, 4.4vw, 3rem); line-height: 1.05; letter-spacing: -.045em; color: var(--text); }
.fg-cleanup h1 span { color: var(--accent); }
.fg-lead { margin: 0; color: var(--muted); font-size: 1.02rem; }

.fg-note {
    margin: 0 0 22px; padding: 14px 18px;
    border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
    border-radius: 15px;
    background: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 12%, transparent), transparent 18rem), var(--panel);
    color: var(--muted); font-size: .9rem;
}
.fg-note strong { color: var(--text); }

.fg-kicker { color: var(--accent); font-size: .74rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }

.fg-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(156px, 1fr)); gap: 12px; margin-bottom: 22px; }
.fg-tile {
    --tc: var(--accent);
    position: relative; display: flex; flex-direction: column; gap: 2px; align-items: flex-start;
    padding: 16px 18px; text-align: left; cursor: pointer;
    border: 1px solid var(--border); border-radius: 15px;
    background: var(--panel); color: var(--text);
    font: inherit; transition: .2s ease;
}
.fg-tile--ok { --tc: var(--ok); } .fg-tile--warn { --tc: var(--warn); } .fg-tile--bad { --tc: var(--bad); } .fg-tile--muted { --tc: var(--muted); }
.fg-tile:hover { transform: translateY(-2px); border-color: var(--border-hover); }
.fg-tile.is-active { border-color: color-mix(in srgb, var(--tc) 55%, transparent); background: color-mix(in srgb, var(--tc) 11%, var(--panel-solid)); }
.fg-tile:focus-visible, .fg-chip:focus-visible, .fg-btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.fg-tile__label { display: inline-flex; align-items: center; gap: 8px; color: var(--muted); font-size: .76rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.fg-tile__label::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--tc); }
.fg-tile__count { color: var(--tc); font-size: 2rem; font-weight: 800; line-height: 1.1; letter-spacing: -.03em; }
.fg-tile--muted .fg-tile__count { color: var(--text); }
.fg-tile__sub { color: var(--bad); font-size: .74rem; font-weight: 700; }

.fg-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 22px; }
.fg-filters .fg-kicker { margin-right: 6px; }
.fg-chip {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 6px 12px; border: 1px solid var(--border); border-radius: 999px;
    background: var(--panel); color: var(--muted); font: inherit; font-size: .85rem; cursor: pointer; transition: .2s ease;
}
.fg-chip small { font-size: .72rem; opacity: .8; }
.fg-chip:hover, .fg-chip.is-active { color: var(--text); border-color: color-mix(in srgb, var(--accent) 40%, transparent); background: var(--accent-soft); }

.fg-panel { border: 1px solid var(--border); border-radius: var(--radius); background: var(--panel); box-shadow: var(--shadow); overflow: hidden; }
.fg-panel__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--border); }
.fg-panel__head h2 { margin: 0; font-size: 1.15rem; letter-spacing: -.02em; color: var(--text); }
.fg-panel__note { padding: 12px 20px 0; color: var(--muted); font-size: .82rem; }
.fg-panel__foot { padding: 12px 20px; border-top: 1px solid var(--border); color: var(--muted); font-size: .82rem; }
.fg-scroll { overflow-x: auto; }

.fg-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    min-height: 36px; padding: 0 14px; border-radius: 11px;
    border: 1px solid var(--border); background: var(--panel); color: var(--text);
    font: inherit; font-size: .85rem; font-weight: 750; cursor: pointer; transition: .2s ease;
}
.fg-btn:hover { transform: translateY(-2px); border-color: var(--border-hover); color: var(--text); }
.fg-btn--primary { background: var(--accent); border-color: var(--accent); color: var(--on-accent); }
.fg-btn--primary:hover { color: var(--on-accent); }
.fg-btn--warn { color: var(--warn); border-color: color-mix(in srgb, var(--warn) 45%, transparent); background: color-mix(in srgb, var(--warn) 10%, transparent); }
.fg-btn--warn:hover { color: var(--warn); }
.fg-btn--bad { color: var(--bad); border-color: color-mix(in srgb, var(--bad) 45%, transparent); background: color-mix(in srgb, var(--bad) 9%, transparent); }
.fg-btn--bad:hover { color: var(--bad); }

.fg-table { width: 100%; min-width: 860px; border-collapse: collapse; background: transparent; margin: 0; }
.fg-table th, .fg-table td { padding: 8px 12px; border: 0; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; background: transparent; color: var(--text); font-size: .85rem; line-height: 1.35; }
.fg-table th { padding-top: 10px; padding-bottom: 10px; color: var(--muted); font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; white-space: nowrap; }
.fg-table tbody tr:last-child td { border-bottom: 0; }
.fg-table tbody tr[data-filter-group]:hover td { background: var(--accent-soft); }
.fg-table tr[hidden] { display: none; }
.fg-table td:nth-child(4) { min-width: 230px; }
.fg-table td:nth-child(5), .fg-table td:nth-child(6) { white-space: nowrap; }
.fg-table td:nth-child(5) { min-width: 150px; }
.fg-table .fg-col-toggle { width: 1%; padding-right: 0; }
.fg-clickable { cursor: pointer; }
.fg-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.fg-table td.fg-details-cell { width: 100%; max-width: 0; }
.fg-clip { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted); }
.fg-wrap { display: block; }
.fg-meta { display: inline; margin-left: 6px; white-space: nowrap; color: var(--muted); font-size: .76rem; }
.fg-name { display: block; font-weight: 700; }
.fg-line2 { display: block; color: var(--muted); font-size: .76rem; line-height: 1.3; }
.fg-el { display: block; }
.fg-table tbody tr[data-filter-group] > td { height: 58px; }
.fg-empty { padding: 36px 16px; text-align: center; color: var(--muted); }
.fg-btn--sm { min-height: 28px; padding: 0 10px; border-radius: 9px; font-size: .78rem; }

.fg-toggle {
    display: inline-grid; place-items: center;
    width: 30px; height: 30px; padding: 0; border-radius: 9px;
    border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
    background: var(--accent-soft); color: var(--accent);
    font-size: 1.25rem; font-weight: 700; line-height: 1; cursor: pointer; transition: .2s ease;
}
.fg-toggle:hover { background: var(--accent); border-color: var(--accent); color: var(--on-accent); }
.fg-toggle:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.fg-toggle[aria-expanded="true"] { transform: rotate(90deg); background: var(--accent); border-color: var(--accent); color: var(--on-accent); }
.fg-detail-row td { padding: 0; background: color-mix(in srgb, var(--accent) 4%, transparent); }
.fg-detail-box { padding: 12px 16px 14px 56px; color: var(--muted); font-size: .84rem; }
.fg-detail-box p { margin: 0 0 8px; color: var(--text); }
.fg-detail-box .fg-kicker { margin: 10px 0 4px; }
.fg-cand { margin-bottom: 8px; }
.fg-cand ul { margin: 4px 0 0; padding-left: 18px; }
.fg-cand li { margin-top: 4px; }
.fg-cand strong, .fg-detail-box strong { color: var(--text); }

.fg-pill {
    --pc: var(--muted);
    display: inline-flex; align-items: center; padding: 2px 9px; border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--pc) 35%, transparent);
    background: color-mix(in srgb, var(--pc) 11%, transparent);
    color: var(--pc); font-size: .74rem; font-weight: 700; white-space: nowrap;
}
.fg-pill--ok { --pc: var(--ok); } .fg-pill--warn { --pc: var(--warn); } .fg-pill--bad { --pc: var(--bad); } .fg-pill--accent { --pc: var(--accent); }
.fg-pill--type { --pc: var(--muted); font-weight: 600; }

.fg-actions { white-space: nowrap; text-align: right; width: 1%; }

.fg-foot-note { margin: 18px 0 0; color: var(--muted); font-size: .82rem; }

.fg-cleanup [hidden] { display: none !important; }
.fg-dialog__ext { white-space: pre-line; max-height: 40vh; overflow: auto; }
.fg-overlay[data-tone="accent"] .fg-dialog { background: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 14rem), var(--panel-solid); }
.fg-overlay[data-tone="accent"] .fg-dialog__icon { color: var(--accent); background: var(--accent-soft); border-color: color-mix(in srgb, var(--accent) 35%, transparent); }
.fg-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.fg-check { display: inline-flex; align-items: center; gap: 7px; color: var(--muted); font-size: .82rem; cursor: pointer; }
.fg-check input, .fg-pick { accent-color: var(--accent); width: 16px; height: 16px; margin: 0; cursor: pointer; }
.fg-pick { vertical-align: middle; margin-right: 8px; }
.fg-panel + .fg-panel { margin-top: 22px; }
.fg-sub-table td, .fg-sub-table th { white-space: normal; }
.fg-sub-table .fg-nowrap { white-space: nowrap; }
.fg-pill--small { font-size: .7rem; }

.fg-search { margin: 0 0 16px; }
.fg-search input {
    width: min(100%, 420px); min-height: 38px; padding: 0 14px; border: 1px solid var(--border); border-radius: 999px;
    background: var(--panel); color: var(--text); font: inherit; font-size: .88rem; outline: none; transition: .2s ease;
}
.fg-search input::placeholder { color: var(--muted); }
.fg-search input:focus { border-color: color-mix(in srgb, var(--accent) 55%, transparent); box-shadow: 0 0 0 3px var(--accent-soft); }
.fg-oo { border-top: 1px solid var(--border); }
.fg-oo summary { display: flex; align-items: center; gap: 10px; padding: 12px 20px; cursor: pointer; color: var(--text); font-weight: 700; font-size: .92rem; list-style: none; }
.fg-oo summary::-webkit-details-marker { display: none; }
.fg-oo summary::before { content: "›"; color: var(--accent); font-size: 1.2rem; transition: transform .15s ease; }
.fg-oo[open] summary::before { transform: rotate(90deg); }
.fg-oo .fg-count { margin-left: auto; }
.fg-oo--none summary { cursor: default; color: var(--muted); font-weight: 500; }
.fg-oo--none summary::before { content: "✓"; color: var(--ok); }

/* Confirmation dialog + "please wait" overlay (FG style, replaces window.confirm) */
.fg-overlay {
    position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 16px;
    background: rgba(8,14,20,.55); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px);
    opacity: 0; transition: opacity .18s ease;
}
.fg-overlay[hidden] { display: none; }
.fg-overlay.is-open { opacity: 1; }
.fg-dialog {
    width: min(460px, 100%); padding: 26px 26px 22px;
    border: 1px solid var(--border-hover); border-radius: var(--radius);
    background: radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--bad) 13%, transparent), transparent 14rem), var(--panel-solid);
    color: var(--text); box-shadow: 0 30px 80px rgba(0,0,0,.35);
    transform: translateY(10px) scale(.98); transition: transform .18s ease;
}
.fg-overlay.is-open .fg-dialog { transform: none; }
.fg-dialog__icon {
    display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin-bottom: 12px;
    border-radius: 12px; font-size: 1.25rem; font-weight: 800; color: var(--bad);
    background: color-mix(in srgb, var(--bad) 14%, transparent); border: 1px solid color-mix(in srgb, var(--bad) 35%, transparent);
}
.fg-dialog h2 { margin: 0 0 8px; font-size: 1.2rem; letter-spacing: -.02em; color: var(--text); }
.fg-dialog p { margin: 0; color: var(--muted); font-size: .92rem; line-height: 1.5; }
.fg-dialog__ext { margin: 12px 0 0; padding: 8px 12px; border-radius: 11px; border: 1px solid var(--border); background: var(--accent-soft); color: var(--text); font-size: .85rem; }
.fg-dialog__ext:empty { display: none; }
.fg-dialog__actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
.fg-btn--bad { color: var(--bad); border-color: color-mix(in srgb, var(--bad) 45%, transparent); background: color-mix(in srgb, var(--bad) 10%, transparent); }
.fg-btn--danger { background: var(--bad); border-color: var(--bad); color: #fff; }
.fg-btn--danger:hover { color: #fff; filter: brightness(1.08); }
.fg-busy { flex-direction: column; gap: 14px; text-align: center; }
.fg-busy .fg-dialog { display: flex; flex-direction: column; align-items: center; gap: 4px; background: var(--panel-solid); }
.fg-busy .fg-dialog p { font-size: .85rem; }
.fg-spinner {
    width: 38px; height: 38px; margin-bottom: 8px; border-radius: 50%;
    border: 3px solid var(--accent-soft); border-top-color: var(--accent); animation: fg-spin .8s linear infinite;
}
@keyframes fg-spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .fg-spinner { animation-duration: 2.4s; } .fg-overlay, .fg-dialog { transition: none; } }
CSS);
$wa->addInlineScript(<<<'JS'
(function () {
    'use strict';

    var lastFocus = null;

    function byId(id) { return document.getElementById(id); }

    function openOverlay(el) {
        el.hidden = false;
        // next frame so the CSS transition runs
        window.requestAnimationFrame(function () { el.classList.add('is-open'); });
    }

    function closeOverlay(el) {
        el.classList.remove('is-open');
        el.hidden = true;
    }

    function showBusy() {
        var busy = byId('fg-busy');
        if (busy) { openOverlay(busy); }
    }

    // Styled confirmation dialog for destructive forms (replaces window.confirm; no inline handlers, CSP friendly).
    function askConfirm(message, details, onYes, opts) {
        opts = opts || {};
        var modal = byId('fg-confirm');
        if (!modal) {
            if (window.confirm(message)) { onYes(); }
            return;
        }

        var titleEl = byId('fg-confirm-title');
        if (!modal.hasAttribute('data-default-title')) {
            modal.setAttribute('data-default-title', titleEl.textContent);
            modal.setAttribute('data-default-yes', byId('fg-confirm-yes').textContent);
        }
        titleEl.textContent = opts.title || modal.getAttribute('data-default-title');
        byId('fg-confirm-yes').textContent = opts.yes || modal.getAttribute('data-default-yes');
        byId('fg-confirm-yes').className = 'fg-btn ' + (opts.tone === 'accent' ? 'fg-btn--primary' : 'fg-btn--danger');
        modal.setAttribute('data-tone', opts.tone === 'accent' ? 'accent' : 'danger');
        byId('fg-confirm-text').textContent = message;
        byId('fg-confirm-ext').textContent = details || '';
        var yes = byId('fg-confirm-yes');
        var no = byId('fg-confirm-no');
        lastFocus = document.activeElement;

        function done(accepted) {
            closeOverlay(modal);
            yes.removeEventListener('click', onYesClick);
            no.removeEventListener('click', onNoClick);
            modal.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onKey, true);
            if (accepted) {
                onYes();
            } else if (lastFocus && lastFocus.focus) {
                lastFocus.focus();
            }
        }
        function onYesClick() { done(true); }
        function onNoClick() { done(false); }
        function onBackdrop(ev) { if (ev.target === modal) { done(false); } }
        function onKey(ev) {
            if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); done(false); }
            if (ev.key === 'Tab') {
                // keep focus inside the dialog
                ev.preventDefault();
                (document.activeElement === no ? yes : no).focus();
            }
        }

        yes.addEventListener('click', onYesClick);
        no.addEventListener('click', onNoClick);
        modal.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKey, true);
        openOverlay(modal);
        no.focus();   // the safe choice has the initial focus
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form && form.getAttribute ? form.getAttribute('data-fg-confirm') : null;

        if (message) {
            event.preventDefault();
            var details = form.getAttribute('data-fg-details') || '';

            // Bulk removal: only ticked rows that are currently visible are submitted.
            var picked = null;
            if (form.hasAttribute('data-fg-bulk')) {
                picked = Array.prototype.slice.call(document.querySelectorAll('.fg-pick')).filter(function (box) {
                    var row = box.closest('tr');
                    return box.checked && row && !row.hidden;
                });
                if (!picked.length) {
                    return;
                }
                message = message.replace('%d', picked.length);
                var names = picked.map(function (box) { return box.getAttribute('data-name'); });
                details = names.slice(0, 12).join('\n') + (names.length > 12 ? '\n…' : '');
            }

            askConfirm(message, details, function () {
                if (picked) {
                    // disabled checkboxes are not submitted, so nothing outside the confirmed list can slip in
                    Array.prototype.slice.call(document.querySelectorAll('.fg-pick')).forEach(function (box) {
                        box.disabled = picked.indexOf(box) === -1;
                    });
                }
                showBusy();
                // let the overlay paint before the navigation starts
                window.setTimeout(function () { HTMLFormElement.prototype.submit.call(form); }, 60);
            }, {
                title: form.getAttribute('data-fg-title'),
                yes: form.getAttribute('data-fg-yes'),
                tone: form.getAttribute('data-fg-tone')
            });
            return;
        }

        showBusy();
    });

    // "Refresh scan" (and any other link marked data-fg-busy) shows the waiting overlay.
    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('a[data-fg-busy]') : null;
        if (link && !event.defaultPrevented && event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            showBusy();
        }
    });

    // Coming back through the browser history must not leave the overlay on screen.
    window.addEventListener('pageshow', function () {
        ['fg-busy', 'fg-confirm'].forEach(function (id) {
            var el = byId(id);
            if (el) { el.classList.remove('is-open'); el.hidden = true; }
        });
    });

    // Web Asset Manager prints inline scripts in <head>, so wait until the table and buttons exist.
    function init() {
        var root = document.querySelector('.fg-cleanup');
        var table = document.getElementById('fg-cleanup-results');
        var counter = document.getElementById('fg-cleanup-visible-count');

        if (!root || !table || !counter) {
            return;
        }

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-filter-group]'));
        var total = rows.length;
        var state = { status: 'all', type: 'all', search: '' };
        var searchBox = byId('fg-search');
        var picks = Array.prototype.slice.call(root.querySelectorAll('.fg-pick'));
        var selectAll = byId('fg-select-all');
        var bulkButton = byId('fg-bulk-button');
        var bulkCount = byId('fg-bulk-count');

        function refreshBulk() {
            var visible = picks.filter(function (box) { var r = box.closest('tr'); return r && !r.hidden; });
            var checked = visible.filter(function (box) { return box.checked; }).length;
            if (bulkButton) { bulkButton.hidden = checked === 0; }
            if (bulkCount) { bulkCount.textContent = checked; }
            if (selectAll) {
                selectAll.checked = visible.length > 0 && checked === visible.length;
                selectAll.indeterminate = checked > 0 && checked < visible.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                picks.forEach(function (box) {
                    var r = box.closest('tr');
                    box.checked = selectAll.checked && r && !r.hidden;
                });
                refreshBulk();
            });
        }
        picks.forEach(function (box) { box.addEventListener('change', refreshBulk); });

        function applyFilters() {
            var visible = 0;

            rows.forEach(function (row) {
                var show = (state.status === 'all'
                    || (state.status === 'thirdparty' ? row.getAttribute('data-core') !== '1' : row.getAttribute('data-filter-group') === state.status))
                    && (state.type === 'all' || row.getAttribute('data-extension-type') === state.type)
                    && (state.search === '' || (row.getAttribute('data-search') || '').indexOf(state.search) !== -1);
                row.hidden = !show;
                var detail = row.nextElementSibling;
                if (detail && detail.classList.contains('fg-detail-row')) {
                    detail.hidden = !show || row.getAttribute('data-expanded') !== '1';
                }
                if (show) {
                    visible++;
                }
            });

            // A row that is filtered out can never stay selected.
            picks.forEach(function (box) {
                var r = box.closest('tr');
                if (r && r.hidden) { box.checked = false; }
            });
            refreshBulk();

            counter.textContent = Joomla.Text._('COM_FGEXTENSIONCLEANUP_VISIBLE_COUNT')
                .replace('%1$d', visible)
                .replace('%2$d', total);
        }

        if (searchBox) {
            searchBox.addEventListener('input', function () {
                state.search = searchBox.value.trim().toLowerCase();
                applyFilters();
            });
            searchBox.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && searchBox.value !== '') {
                    searchBox.value = '';
                    state.search = '';
                    applyFilters();
                }
            });
        }

        // Both filter groups are plain button sets: [data-fg-status] tiles and [data-fg-type] chips.
        root.addEventListener('click', function (event) {
            var toggle = event.target.closest('button[data-fg-toggle]');
            if (!toggle) {
                var cell = event.target.closest('td.fg-clickable');
                if (cell && root.contains(cell)) {
                    toggle = cell.parentNode.querySelector('button[data-fg-toggle]');
                }
            }
            if (toggle && root.contains(toggle)) {
                var row = toggle.closest('tr');
                var expanded = row.getAttribute('data-expanded') !== '1';
                row.setAttribute('data-expanded', expanded ? '1' : '0');
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                row.nextElementSibling.hidden = !expanded;
                return;
            }

            var button = event.target.closest('button[data-fg-status], button[data-fg-type]');
            if (!button || !root.contains(button)) {
                return;
            }

            var key = button.hasAttribute('data-fg-status') ? 'status' : 'type';
            state[key] = button.getAttribute(key === 'status' ? 'data-fg-status' : 'data-fg-type');

            Array.prototype.forEach.call(root.querySelectorAll('button[data-fg-' + key + ']'), function (item) {
                var active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            applyFilters();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
JS);
?>
<?php
$removableCount = 0;
foreach ($this->rows as $item) {
    if (empty($item['core']) && in_array((string) $item['record']->type, ['plugin', 'library', 'component'], true)
        && $item['analysis']['status'] === 'orphan' && $item['analysis']['removable'] && empty($item['record']->protected)) {
        $removableCount++;
    }
}
?>
<div class="fg-cleanup">
    <div class="fg-hero">
        <span class="fg-eyebrow"><?php echo $e($t('EYEBROW')); ?></span>
        <h1><?php echo $titleHtml; ?></h1>
        <p class="fg-lead"><?php echo $e($t('SUBTITLE')); ?></p>
    </div>

    <div class="fg-note">
        <strong><?php echo $e($t('SAFE_MODE')); ?></strong> <?php echo $e($t('SAFE_MODE_TEXT')); ?>
    </div>

    <div class="fg-tiles" role="group" aria-label="<?php echo $e($t('FILTER_STATUS')); ?>">
        <?php foreach ($statusTiles as $value => $tile) : ?>
            <button type="button"
                    class="fg-tile fg-tile--<?php echo $e($tile['variant']); ?><?php echo $value === 'all' ? ' is-active' : ''; ?>"
                    data-fg-status="<?php echo $e($value); ?>"
                    aria-pressed="<?php echo $value === 'all' ? 'true' : 'false'; ?>">
                <span class="fg-tile__label"><?php echo $e($tile['label']); ?></span>
                <span class="fg-tile__count"><?php echo (int) $tile['count']; ?></span>
                <?php if (!empty($tile['sub'])) : ?><span class="fg-tile__sub"><?php echo $e($tile['sub']); ?></span><?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="fg-filters" role="group" aria-label="<?php echo $e($t('FILTER_TYPE')); ?>">
        <span class="fg-kicker"><?php echo $e($t('FILTER_TYPE')); ?></span>
        <button type="button" class="fg-chip is-active" data-fg-type="all" aria-pressed="true">
            <?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_FILTER_ALL_TYPES', $total)); ?>
        </button>
        <?php foreach ($typeCounts as $type => $typeCount) : ?>
            <button type="button" class="fg-chip" data-fg-type="<?php echo $e($type); ?>" aria-pressed="false">
                <?php echo $e($typeLabels[$type] ?? $type); ?> <small><?php echo (int) $typeCount; ?></small>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="fg-search">
        <input type="search" id="fg-search" autocomplete="off" placeholder="<?php echo $e($t('SEARCH_PLACEHOLDER')); ?>" aria-label="<?php echo $e($t('SEARCH_PLACEHOLDER')); ?>">
    </div>

    <div class="fg-panel">
        <div class="fg-panel__head">
            <h2><?php echo $e($t('RESULTS_TITLE')); ?></h2>
            <div class="fg-head-actions">
                <?php if ($this->canAdmin && $removableCount > 0) : ?>
                    <label class="fg-check"><input type="checkbox" id="fg-select-all"> <?php echo $e($t('BULK_SELECT_ALL')); ?></label>
                    <form id="fg-bulk-form" method="post" action="<?php echo $e($baseUrl); ?>" data-fg-bulk
                          data-fg-confirm="<?php echo $e($t('CONFIRM_BULK')); ?>">
                        <input type="hidden" name="task" value="removeOrphanRecords">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" id="fg-bulk-button" class="fg-btn fg-btn--danger" hidden><?php echo $e($t('BTN_BULK_REMOVE')); ?> (<span id="fg-bulk-count">0</span>)</button>
                    </form>
                <?php endif; ?>
                <a class="fg-btn" href="<?php echo $e($baseUrl); ?>" data-fg-busy><?php echo $e($t('REFRESH')); ?></a>
            </div>
        </div>

        <?php if ((int) $counts['other'] > 0) : ?>
            <div class="fg-panel__note"><?php echo $e($t('OTHER_NOTE')); ?></div>
        <?php endif; ?>

        <div class="fg-scroll">
            <table class="fg-table" id="fg-cleanup-results">
                <thead>
                    <tr>
                        <th class="fg-col-toggle"><span class="fg-sr"><?php echo $e($t('TOGGLE_DETAILS')); ?></span></th>
                        <th>ID</th>
                        <th><?php echo $e($t('TH_TYPE')); ?></th>
                        <th><?php echo $e($t('TH_EXTENSION')); ?></th>
                        <th><?php echo $e($t('TH_ELEMENT')); ?></th>
                        <th>Folder</th>
                        <th><?php echo $e($t('TH_STATUS')); ?></th>
                        <th><?php echo $e($t('TH_DETAILS')); ?></th>
                        <th class="fg-actions"><?php echo $e($t('TH_ACTION')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$this->rows) : ?>
                    <tr><td colspan="9" class="fg-empty"><?php echo $e($t('NO_RECORDS')); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($this->rows as $item) : ?>
                        <?php
                        $record = $item['record'];
                        $analysis = $item['analysis'];
                        $status = $analysis['status'];
                        $type = (string) ($record->type ?? 'unknown');
                        $isCore = !empty($item['core']);
                        $filterGroup = $isCore ? 'core' : (in_array($status, ['ok', 'repairable', 'ambiguous', 'orphan'], true) ? $status : 'other');
                        $pill = match ($status) {
                            'ok' => 'ok',
                            'repairable', 'manifest_missing' => 'warn',
                            'orphan', 'unsafe' => 'bad',
                            'ambiguous' => 'accent',
                            default => 'muted',
                        };
                        $hasClient = in_array($type, ['component', 'module', 'template', 'language'], true);
                        $clientLabel = match ((int) ($record->client_id ?? 0)) {
                            1 => $t('CLIENT_ADMIN'),
                            3 => $t('CLIENT_API'),
                            default => $t('CLIENT_SITE'),
                        };
                        $hasMore = !empty($analysis['candidates']) || !empty($analysis['package']) || !empty($analysis['blocked_paths']);
                        ?>
                        <tr data-filter-group="<?php echo $e($filterGroup); ?>" data-core="<?php echo $isCore ? '1' : '0'; ?>" data-extension-type="<?php echo $e($type); ?>"
                            data-search="<?php echo $e(mb_strtolower((int) $record->extension_id . ' ' . $record->name . ' ' . $record->element . ' ' . ($record->folder ?? '') . ' ' . $type)); ?>">
                            <td class="fg-col-toggle">
                                <?php if ($hasMore) : ?>
                                    <button type="button" class="fg-toggle" data-fg-toggle aria-expanded="false" aria-label="<?php echo $e($t('TOGGLE_DETAILS')); ?>" title="<?php echo $e($t('TOGGLE_DETAILS')); ?>">›</button>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $record->extension_id; ?></td>
                            <td><span class="fg-pill fg-pill--type"><?php echo $e($typeLabels[$type] ?? $type); ?></span></td>
                            <td>
                                <?php
                                $nameMeta = [];
                                if ($isCore) {
                                    $nameMeta[] = $t('F_CORE');
                                }
                                if (!empty($record->protected)) {
                                    $nameMeta[] = 'protected';
                                }
                                if ((int) ($record->package_id ?? 0) > 0) {
                                    $nameMeta[] = 'package_id: ' . (int) $record->package_id;
                                }
                                ?>
                                <span class="fg-name"><?php echo $e($record->name); ?></span>
                                <?php if ($nameMeta) : ?><span class="fg-line2"><?php echo $e(implode(' · ', $nameMeta)); ?></span><?php endif; ?>
                            </td>
                            <td>
                                <code class="fg-el"><?php echo $e($record->element); ?></code>
                                <?php if ($hasClient) : ?><span class="fg-line2"><?php echo $e($clientLabel); ?></span><?php endif; ?>
                            </td>
                            <td><?php echo $type === 'plugin' ? $e((string) ($record->folder ?? '') ?: $t('EMPTY_FOLDER')) : '—'; ?></td>
                            <td><span class="fg-pill fg-pill--<?php echo $e($pill); ?>"><?php echo $e($analysis['label']); ?></span></td>
                            <td class="fg-details-cell<?php echo $hasMore ? ' fg-clickable' : ''; ?>">
                                <span class="<?php echo $status === 'ok' ? 'fg-clip' : 'fg-wrap'; ?>" title="<?php echo $e($analysis['detail']); ?>"><?php echo $e($analysis['detail']); ?></span>
                            </td>
                            <td class="fg-actions">
                                <?php if ($this->canAdmin && $type === 'plugin' && $status === 'repairable' && !empty($analysis['repair'])) : ?>
                                    <form method="post" action="<?php echo $e($baseUrl); ?>" style="display:inline">
                                        <input type="hidden" name="task" value="repairFolder">
                                        <input type="hidden" name="extension_id" value="<?php echo (int) $record->extension_id; ?>">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button type="submit" class="fg-btn fg-btn--sm fg-btn--warn"><?php echo $e($t('BTN_REPAIR')); ?></button>
                                    </form>
                                <?php elseif ($this->canAdmin && in_array($type, ['plugin', 'library', 'component'], true) && $status === 'orphan' && $analysis['removable'] && empty($record->protected)) : ?>
                                    <input type="checkbox" class="fg-pick" form="fg-bulk-form" name="extension_id[]" value="<?php echo (int) $record->extension_id; ?>"
                                           data-name="<?php echo $e($record->name . ' (' . $type . ': ' . $record->element . ')'); ?>"
                                           aria-label="<?php echo $e($t('BULK_SELECT_ROW')); ?>">
                                    <form method="post" action="<?php echo $e($baseUrl); ?>" style="display:inline" data-fg-confirm="<?php echo $e($t($type === 'component' ? 'CONFIRM_REMOVE_COMPONENT' : 'CONFIRM_REMOVE')); ?>" data-fg-details="<?php echo $e($record->name . ' (' . $type . ': ' . $record->element . ')'); ?>">
                                        <input type="hidden" name="task" value="removeOrphanRecord">
                                        <input type="hidden" name="extension_id" value="<?php echo (int) $record->extension_id; ?>">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button type="submit" class="fg-btn fg-btn--sm fg-btn--bad"><?php echo $e($t('BTN_REMOVE')); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($hasMore) : ?>
                        <tr class="fg-detail-row" hidden>
                            <td colspan="9">
                                <div class="fg-detail-box">
                                    <p><?php echo $e($analysis['detail']); ?></p>

                                    <?php if (!empty($analysis['candidates'])) : ?>
                                        <div class="fg-kicker"><?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_CANDIDATES', count($analysis['candidates']))); ?></div>
                                        <?php foreach ($analysis['candidates'] as $candidate) : ?>
                                            <div class="fg-cand">
                                                <?php if (!empty($candidate['group'])) : ?>
                                                    <div><strong><?php echo $e($t('LBL_GROUP')); ?></strong> <code><?php echo $e($candidate['group']); ?></code></div>
                                                <?php endif; ?>
                                                <?php if (!empty($candidate['directory'])) : ?>
                                                    <div><strong><?php echo $e($t('LBL_DIR')); ?></strong> <code><?php echo $e($candidate['directory']); ?></code></div>
                                                <?php endif; ?>
                                                <div>
                                                    <span class="fg-pill fg-pill--<?php echo !empty($candidate['valid']) ? 'ok' : 'warn'; ?>">
                                                        <?php echo $e(!empty($candidate['valid']) ? $t('MANIFEST_CONFIRMED') : $t('MANIFEST_UNCONFIRMED')); ?>
                                                    </span>
                                                    <?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_VALID_MANIFESTS', (int) ($candidate['valid_count'] ?? 0))); ?>
                                                </div>
                                                <?php if (!empty($candidate['manifests'])) : ?>
                                                    <ul>
                                                        <?php foreach ($candidate['manifests'] as $manifest) : ?>
                                                            <li>
                                                                <code><?php echo $e($manifest['path']); ?></code>
                                                                <span class="fg-pill fg-pill--<?php echo !empty($manifest['valid']) ? 'ok' : 'muted'; ?>">
                                                                    <?php echo $e(!empty($manifest['valid']) ? $t('BADGE_VALID') : $t('BADGE_INVALID')); ?>
                                                                </span>
                                                                <span class="fg-meta"><?php echo $e($manifest['reason']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else : ?>
                                                    <div class="fg-meta"><?php echo $e($t('NO_XML')); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (!empty($analysis['package'])) : ?>
                                        <div class="fg-kicker"><?php echo $e($t('PKG_SUMMARY')); ?></div>
                                        <div class="fg-cand">
                                            <strong><?php echo $e($t('PKG_DECLARED')); ?></strong>
                                            <?php if (!empty($analysis['package']['declared'])) : ?>
                                                <ul>
                                                    <?php foreach ($analysis['package']['declared'] as $declared) : ?>
                                                        <?php $matched = !empty($declared['matches']); ?>
                                                        <li>
                                                            <span class="fg-pill fg-pill--<?php echo $matched ? 'ok' : 'warn'; ?>"><?php echo $e($matched ? $t('PKG_DB_FOUND') : $t('PKG_NO_DB')); ?></span>
                                                            <strong><?php echo $e($declared['type']); ?><?php echo $declared['tag'] === 'folder' ? ' (folder)' : ''; ?>:</strong> <code><?php echo $e($declared['id']); ?></code>
                                                            <?php if ($declared['client'] !== '') : ?>· <?php echo $e($declared['client']); ?><?php endif; ?>
                                                            <?php if ($declared['group'] !== '') : ?>· <?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_PKG_GROUP', $declared['group'])); ?><?php endif; ?>
                                                            <?php if ($declared['filename'] !== '') : ?><span class="fg-meta"><?php echo $e($declared['tag'] === 'folder' ? $t('LBL_DIR') : $t('LBL_FILE')); ?> <?php echo $e($declared['filename']); ?></span><?php endif; ?>
                                                            <?php if (!$matched && !empty($declared['match_reason'])) : ?>
                                                                <span class="fg-meta"><?php echo $e($declared['match_reason']); ?></span>
                                                            <?php endif; ?>
                                                            <?php foreach ($declared['matches'] as $match) : ?>
                                                                <span class="fg-meta">ID <?php echo (int) $match['extension_id']; ?> · <?php echo $e($match['name']); ?> · element <code><?php echo $e($match['element']); ?></code> · <?php echo $e(!empty($match['belongs_to_package']) ? $t('PKG_BELONGS') : $t('PKG_NOT_BELONGS')); ?></span>
                                                            <?php endforeach; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <div class="fg-meta"><?php echo $e($t('PKG_NO_DECLARED')); ?></div>
                                            <?php endif; ?>

                                            <div style="margin-top:10px"><strong><?php echo $e($t('PKG_REGISTERED')); ?></strong></div>
                                            <?php if (!empty($analysis['package']['registered'])) : ?>
                                                <ul>
                                                    <?php foreach ($analysis['package']['registered'] as $child) : ?>
                                                        <li><?php echo $e($child['type']); ?> · <code><?php echo $e($child['element']); ?></code> · ID <?php echo (int) $child['extension_id']; ?> · <?php echo $e($child['name']); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else : ?>
                                                <div class="fg-meta"><?php echo $e($t('PKG_NO_REGISTERED')); ?></div>
                                            <?php endif; ?>
                                            <div class="fg-meta" style="margin-top:8px"><?php echo $e($t('PKG_DIAG_NOTE')); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($analysis['blocked_paths'])) : ?>
                                        <div class="fg-kicker"><?php echo $e($t('BLOCKED_PATHS')); ?></div>
                                        <?php foreach ($analysis['blocked_paths'] as $blockedPath) : ?>
                                            <div><code><?php echo $e($blockedPath); ?></code></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="fg-panel__foot" id="fg-cleanup-visible-count" aria-live="polite">
            <?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_VISIBLE_COUNT', $total, $total)); ?>
        </div>
    </div>

    <?php $orphanSites = $this->updateSites['orphans']; ?>
    <div class="fg-panel" id="fg-update-sites">
        <div class="fg-panel__head">
            <h2><?php echo $e($t('US_TITLE')); ?></h2>
            <?php $removableSites = count(array_filter($orphanSites, static fn (array $s): bool => $s['removable'])); ?>
            <?php if ($this->canAdmin && $removableSites > 0) : ?>
                <form method="post" action="<?php echo $e($baseUrl); ?>" data-fg-confirm="<?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_CONFIRM_US_ALL', $removableSites)); ?>">
                    <input type="hidden" name="task" value="removeUpdateSites">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="fg-btn fg-btn--bad"><?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_US_BTN_REMOVE_ALL', $removableSites)); ?></button>
                </form>
            <?php endif; ?>
        </div>
        <div class="fg-panel__note"><?php echo $e(Text::sprintf('COM_FGEXTENSIONCLEANUP_US_NOTE', (int) $this->updateSites['total'])); ?></div>
        <?php if (!$orphanSites) : ?>
            <div class="fg-empty"><?php echo $e($t('US_NONE')); ?></div>
        <?php else : ?>
            <div class="fg-scroll">
                <table class="fg-table fg-sub-table">
                    <thead><tr>
                        <th>ID</th><th><?php echo $e($t('TH_NAME')); ?></th><th>URL</th><th><?php echo $e($t('US_TH_REASON')); ?></th>
                        <th class="fg-actions"><?php echo $e($t('TH_ACTION')); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($orphanSites as $site) : ?>
                        <tr>
                            <td><?php echo (int) $site['id']; ?></td>
                            <td><span class="fg-name"><?php echo $e($site['name']); ?></span>
                                <span class="fg-line2"><?php echo $e($site['type'] . ($site['enabled'] ? '' : ' · ' . $t('US_DISABLED'))); ?></span></td>
                            <td><code class="fg-el" style="word-break:break-all"><?php echo $e($site['location']); ?></code></td>
                            <td><?php echo $e($site['reason']); ?></td>
                            <td class="fg-actions">
                                <?php if ($this->canAdmin && $site['removable']) : ?>
                                    <form method="post" action="<?php echo $e($baseUrl); ?>" style="display:inline"
                                          data-fg-confirm="<?php echo $e($t('CONFIRM_US')); ?>" data-fg-details="<?php echo $e($site['name'] . ' (#' . $site['id'] . ')'); ?>">
                                        <input type="hidden" name="task" value="removeUpdateSite">
                                        <input type="hidden" name="update_site_id" value="<?php echo (int) $site['id']; ?>">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button type="submit" class="fg-btn fg-btn--sm fg-btn--bad"><?php echo $e($t('BTN_REMOVE')); ?></button>
                                    </form>
                                <?php elseif (!$site['removable']) : ?>
                                    <span class="fg-line2"><?php echo $e($t('US_PROTECTED')); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $oo = $this->otherOrphans;
    $ooLimit = (int) ($oo['limit'] ?? 100);
    $ooSections = [
        'modules' => $t('OO_MODULES'),
        'menu' => $t('OO_MENU'),
        'schemas' => $t('OO_SCHEMAS'),
        'links' => $t('OO_LINKS'),
    ];
    $ooState = static fn ($published): string => match ((int) $published) {
        1 => Text::_('COM_FGEXTENSIONCLEANUP_OO_PUBLISHED'),
        0 => Text::_('COM_FGEXTENSIONCLEANUP_OO_UNPUBLISHED'),
        default => Text::_('COM_FGEXTENSIONCLEANUP_OO_TRASHED'),
    };
    ?>
    <div class="fg-panel" id="fg-other-orphans">
        <div class="fg-panel__head"><h2><?php echo $e($t('OO_TITLE')); ?></h2></div>
        <div class="fg-panel__note"><?php echo $e($t('OO_NOTE')); ?></div>
        <?php foreach ($ooSections as $key => $label) : ?>
            <?php $items = $oo[$key] ?? []; $n = count($items); ?>
            <?php if ($n === 0) : ?>
                <details class="fg-oo fg-oo--none"><summary><?php echo $e($label); ?> <span class="fg-count"><?php echo $e($t('OO_NONE')); ?></span></summary></details>
                <?php continue; ?>
            <?php endif; ?>
            <details class="fg-oo">
                <summary><?php echo $e($label); ?>
                    <span class="fg-pill fg-pill--bad fg-count"><?php echo $n > $ooLimit ? $ooLimit . '+' : $n; ?></span></summary>
                <div class="fg-scroll">
                    <table class="fg-table fg-sub-table">
                        <thead><tr>
                        <?php if ($key === 'modules') : ?>
                            <th>ID</th><th><?php echo $e($t('TH_TITLE')); ?></th><th><?php echo $e($t('OO_TH_MODULE')); ?></th><th><?php echo $e($t('OO_TH_POSITION')); ?></th><th><?php echo $e($t('TH_STATUS')); ?></th>
                        <?php elseif ($key === 'menu') : ?>
                            <th>ID</th><th><?php echo $e($t('TH_TITLE')); ?></th><th><?php echo $e($t('OO_TH_MENU')); ?></th><th>Link</th><th><?php echo $e($t('TH_STATUS')); ?></th>
                        <?php elseif ($key === 'schemas') : ?>
                            <th><?php echo $e($t('OO_TH_EXT_ID')); ?></th><th><?php echo $e($t('OO_TH_VERSION')); ?></th>
                        <?php else : ?>
                            <th><?php echo $e($t('OO_TH_SITE')); ?></th><th><?php echo $e($t('TH_NAME')); ?></th><th><?php echo $e($t('OO_TH_EXT_ID')); ?></th>
                        <?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach (array_slice($items, 0, $ooLimit) as $row) : ?>
                            <tr>
                            <?php if ($key === 'modules') : ?>
                                <td><?php echo (int) $row->id; ?></td>
                                <td><span class="fg-name"><?php echo $e($row->title); ?></span></td>
                                <td><code class="fg-el"><?php echo $e($row->module); ?></code><span class="fg-line2"><?php echo $e((int) $row->client_id === 1 ? $t('CLIENT_ADMIN') : $t('CLIENT_SITE')); ?></span></td>
                                <td><?php echo $e((string) $row->position ?: '—'); ?></td>
                                <td><?php echo $e($ooState($row->published)); ?></td>
                            <?php elseif ($key === 'menu') : ?>
                                <td><?php echo (int) $row->id; ?></td>
                                <td><span class="fg-name"><?php echo $e($row->title); ?></span></td>
                                <td><?php echo $e($row->menutype); ?></td>
                                <td><code class="fg-el" style="word-break:break-all"><?php echo $e($row->link); ?></code></td>
                                <td><?php echo $e($ooState($row->published)); ?></td>
                            <?php elseif ($key === 'schemas') : ?>
                                <td><?php echo (int) $row->extension_id; ?></td>
                                <td><code class="fg-el"><?php echo $e($row->version_id); ?></code></td>
                            <?php else : ?>
                                <td><?php echo (int) $row->update_site_id; ?></td>
                                <td><?php echo $e((string) ($row->name ?? '—')); ?></td>
                                <td><?php echo (int) $row->extension_id; ?></td>
                            <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <?php if ($this->canAdmin) : ?>
    <div class="fg-panel" id="fg-backups">
        <div class="fg-panel__head"><h2><?php echo $e($t('BK_TITLE')); ?></h2></div>
        <div class="fg-panel__note"><?php echo $e($t('BK_NOTE')); ?></div>
        <?php if (!$this->backups) : ?>
            <div class="fg-empty"><?php echo $e($t('BK_NONE')); ?></div>
        <?php else : ?>
            <div class="fg-scroll">
                <table class="fg-table fg-sub-table">
                    <thead><tr>
                        <th><?php echo $e($t('BK_TH_CREATED')); ?></th><th><?php echo $e($t('TH_TYPE')); ?></th><th><?php echo $e($t('TH_NAME')); ?></th>
                        <th>ID</th><th><?php echo $e($t('BK_TH_SIZE')); ?></th><th class="fg-actions"><?php echo $e($t('TH_ACTION')); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($this->backups as $backup) : ?>
                        <?php $kindLabel = $backup['kind'] === 'update_site' ? $t('BK_KIND_US') : ($typeLabels[$backup['type']] ?? $backup['type']); ?>
                        <tr>
                            <td class="fg-nowrap"><?php echo $e(HTMLHelper::_('date', $backup['mtime'], Text::_('DATE_FORMAT_LC6'))); ?></td>
                            <td><span class="fg-pill fg-pill--type"><?php echo $e($backup['valid'] ? $kindLabel : $t('BK_INVALID')); ?></span></td>
                            <td><span class="fg-name"><?php echo $e($backup['name']); ?></span>
                                <span class="fg-line2"><code class="fg-el" style="word-break:break-all"><?php echo $e($backup['file']); ?></code></span></td>
                            <td><?php echo $backup['valid'] ? (int) $backup['id'] : '—'; ?></td>
                            <td class="fg-nowrap"><?php echo $e(number_format(max(1, (int) round($backup['size'] / 1024)))); ?> kB</td>
                            <td class="fg-actions">
                                <?php if ($backup['valid']) : ?>
                                    <form method="post" action="<?php echo $e($baseUrl); ?>" style="display:inline"
                                          data-fg-confirm="<?php echo $e($t('CONFIRM_RESTORE')); ?>" data-fg-details="<?php echo $e($backup['name'] . ' (' . $kindLabel . ' #' . $backup['id'] . ')'); ?>"
                                          data-fg-title="<?php echo $e($t('MODAL_TITLE_RESTORE')); ?>" data-fg-yes="<?php echo $e($t('BTN_RESTORE')); ?>" data-fg-tone="accent">
                                        <input type="hidden" name="task" value="restoreBackup">
                                        <input type="hidden" name="file" value="<?php echo $e($backup['file']); ?>">
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button type="submit" class="fg-btn fg-btn--sm fg-btn--primary"><?php echo $e($t('BTN_RESTORE')); ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?php echo $e($baseUrl); ?>" style="display:inline"
                                      data-fg-confirm="<?php echo $e($t('CONFIRM_DELETE_BACKUP')); ?>" data-fg-details="<?php echo $e($backup['file']); ?>"
                                      data-fg-title="<?php echo $e($t('MODAL_TITLE_DELETE_BACKUP')); ?>" data-fg-yes="<?php echo $e($t('BTN_DELETE_BACKUP')); ?>">
                                    <input type="hidden" name="task" value="deleteBackup">
                                    <input type="hidden" name="file" value="<?php echo $e($backup['file']); ?>">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <button type="submit" class="fg-btn fg-btn--sm fg-btn--bad"><?php echo $e($t('BTN_DELETE_BACKUP')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="fg-foot-note"><?php echo $e($t('FOOTER_NOTE')); ?></p>

    <div class="fg-overlay" id="fg-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="fg-confirm-title" aria-describedby="fg-confirm-text">
        <div class="fg-dialog">
            <span class="fg-dialog__icon" aria-hidden="true">!</span>
            <h2 id="fg-confirm-title"><?php echo $e($t('MODAL_TITLE')); ?></h2>
            <p id="fg-confirm-text"></p>
            <div class="fg-dialog__ext" id="fg-confirm-ext"></div>
            <div class="fg-dialog__actions">
                <button type="button" class="fg-btn" id="fg-confirm-no"><?php echo $e($t('BTN_CANCEL')); ?></button>
                <button type="button" class="fg-btn fg-btn--danger" id="fg-confirm-yes"><?php echo $e($t('BTN_REMOVE')); ?></button>
            </div>
        </div>
    </div>

    <div class="fg-overlay fg-busy" id="fg-busy" hidden role="status" aria-live="polite">
        <div class="fg-dialog">
            <div class="fg-spinner" aria-hidden="true"></div>
            <h2><?php echo $e($t('PLEASE_WAIT')); ?></h2>
            <p><?php echo $e($t('PLEASE_WAIT_SUB')); ?></p>
        </div>
    </div>
</div>
