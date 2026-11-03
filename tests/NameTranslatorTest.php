<?php
use FGcodework\Component\FgExtensionCleanup\Administrator\Service\NameTranslator;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

test('names: only upper-case language keys are treated as keys', function () {
    foreach (['COM_FGCHANGELOGS', 'PLG_SYSTEM_CACHE', 'MOD_X1'] as $key) { same(true, NameTranslator::isLanguageKey($key), $key); }
    foreach (['com_fgsystemreport', 'FG Changelogs', 'COMPONENT', 'Com_Foo', '', 'COM_'] as $plain) { same(false, NameTranslator::isLanguageKey($plain), $plain); }
});

test('names: plain names are returned untouched and load no language file', function () {
    Factory::$loaded = [];
    same('com_fgsystemreport', NameTranslator::translate(rec(['type' => 'component', 'element' => 'com_fgsystemreport', 'name' => 'com_fgsystemreport'])));
    same([], Factory::$loaded);
});

test('names: a language key is translated once its language file is loaded', function () {
    Factory::$loaded = [];
    Text::$strings['COM_DEMOTRANS'] = 'Demo Translated';
    same('Demo Translated', NameTranslator::translate(rec(['type' => 'component', 'element' => 'com_demotrans', 'name' => 'COM_DEMOTRANS', 'client_id' => 1])));
    ok(in_array('com_demotrans.sys@' . JPATH_ADMINISTRATOR, Factory::$loaded, true), 'sys file of the component was loaded');
});

test('names: an unresolvable key and an unsafe element fall back to the raw key', function () {
    unset(Text::$strings['COM_NOPE_X']);
    Text::$strings = array_diff_key(Text::$strings, ['COM_NOPE_X' => 1]);
    // the Text stub echoes "!!MISSING:" for unknown keys, which is not the key itself - emulate Joomla by registering the key to itself
    Text::$strings['COM_NOPE_X'] = 'COM_NOPE_X';
    same('COM_NOPE_X', NameTranslator::translate(rec(['type' => 'component', 'element' => 'com_nope_x', 'name' => 'COM_NOPE_X'])));
    Factory::$loaded = [];
    Text::$strings['COM_EVIL_ONE'] = 'Should not be used';
    same('COM_EVIL_ONE', NameTranslator::translate(rec(['type' => 'component', 'element' => '../../etc', 'name' => 'COM_EVIL_ONE'])));
    same([], Factory::$loaded);
});
