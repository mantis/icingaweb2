<?php
// @codeCoverageIgnoreStart
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright  2013 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Form\Preference;

use DateTimeZone;
use Zend_Config;
use Zend_Form_Element_Select;
use Icinga\Web\Form;
use Icinga\Util\Translator;

/**
 * General user preferences
 */
class GeneralForm extends Form
{
    /**
     * Add a select field for setting the user's language
     *
     * Possible values are determined by Translator::getAvailableLocaleCodes.
     * Also, a 'use browser language' checkbox is added in order to allow a user to discard his setting
     */
    private function addLanguageSelection()
    {
        $languages = array();
        foreach (Translator::getAvailableLocaleCodes() as $language) {
            $languages[$language] = $language;
        }
        $languages[Translator::DEFAULT_LOCALE] = Translator::DEFAULT_LOCALE;
        $useBrowserLanguage = $this->getRequest()->getParam(
            'browser_language',
            !$this->getUserPreferences()->has('app.language')
        );

        $this->addElement(
            'checkbox',
            'browser_language',
            array(
                'label'     => t('Use your browser\'s language suggestions'),
                'value'     => $useBrowserLanguage,
                'required'  => true
            )
        );
        $selectOptions = array(
            'label'         => t('Your Current Language'),
            'required'      => !$useBrowserLanguage,
            'multiOptions'  => $languages,
            'helptext'      => t('Use the following language to display texts and messages'),
            'value'         => substr(setlocale(LC_ALL, 0), 0, 5)
        );
        if ($useBrowserLanguage) {
            $selectOptions['disabled'] = 'disabled';
        }
        $this->addElement('select', 'language', $selectOptions);
        $this->enableAutoSubmit(array('browser_language'));
    }

    /**
     * Add a select field for setting the user's timezone.
     *
     * Possible values are determined by DateTimeZone::listIdentifiers
     * Also, a 'use default format' checkbox is added in order to allow a user to discard his overwritten setting
     *
     * @param Zend_Config $cfg The "global" section of the config.ini to be used as default value
     */
    private function addTimezoneSelection(Zend_Config $cfg)
    {
        $tzList = array();
        foreach (DateTimeZone::listIdentifiers() as $tz) {
            $tzList[$tz] = $tz;
        }
        $helptext = 'Use the following timezone for dates and times';
        $prefs = $this->getUserPreferences();
        $useGlobalTimezone = $this->getRequest()->getParam('default_timezone', !$prefs->has('app.timezone'));

        $selectTimezone = new Zend_Form_Element_Select(
            array(
                'name'          => 'timezone',
                'label'         =>  'Your Current Timezone',
                'required'      =>  !$useGlobalTimezone,
                'multiOptions'  =>  $tzList,
                'helptext'      =>  $helptext,
                'value'         =>  $prefs->get('app.timezone', $cfg->get('timezone', date_default_timezone_get()))
            )
        );
        $this->addElement(
            'checkbox',
            'default_timezone',
            array(
                'label'         => 'Use Default Timezone',
                'value'         => $useGlobalTimezone,
                'required'      => true
            )
        );
        if ($useGlobalTimezone) {
            $selectTimezone->setAttrib('disabled', 1);
        }
        $this->addElement($selectTimezone);
        $this->enableAutoSubmit(array('default_timezone'));
    }

    /**
     * Create the general form, using the global configuration as fallback values for preferences
     *
     * @see Form::create()
     */
    public function create()
    {
        $this->setName('form_preference_set');

        $config = $this->getConfiguration();
        $global = $config->global;
        if ($global === null) {
            $global = new Zend_Config(array());
        }

        $this->addLanguageSelection();
        $this->addTimezoneSelection($global);

        $this->setSubmitLabel('Save Changes');

        $this->addElement(
            'checkbox',
            'show_benchmark',
            array(
                'label' => 'Use benchmark',
                'value' => $this->getUserPreferences()->get('app.show_benchmark')
            )
        );
    }

    /**
     * Return an array containing the preferences set in this form
     *
     * @return array
     */
    public function getPreferences()
    {
        $values = $this->getValues();
        return array(
            'app.language'          => $values['browser_language'] ? null : $values['language'],
            'app.timezone'          => $values['default_timezone'] ? null : $values['timezone'],
            'app.show_benchmark'    => $values['show_benchmark'] === '1' ? true : false
        );
    }
}
// @codeCoverageIgnoreEnd
