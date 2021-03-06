<?php
/**
 * <!--
 * This file is part of the adventure php framework (APF) published under
 * http://adventure-php-framework.org.
 *
 * The APF is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The APF is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with the APF. If not, see http://www.gnu.org/licenses/lgpl-3.0.txt.
 * -->
 */
namespace APF\tools\form\taglib;

use APF\tools\form\FormException;
use DateTime;

/**
 * Represents a APF form time control.
 *
 * @author Werner Liemberger
 * @version
 * Version 0.1, 21.2.2011<br />
 */
class TimeSelectorTag extends AbstractFormControl {

   protected $hoursRange;
   protected $offsetNames;
   protected $minutesInterval;
   protected $showSeconds = true;

   /**
    * Initializes the member variables.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   public function __construct() {

      // initialize the offset names
      $this->offsetNames = ['Hours' => 'Hours', 'Minutes' => 'Minutes', 'Seconds' => 'Seconds'];

      // initialize the year range
      $this->hoursRange['Start'] = 00;
      $this->hoursRange['End'] = 23;
      $this->minutesInterval = 1;
      $this->showSeconds = true;
   }

   /**
    * Creates the children select fields for the time control.
    *
    * @author Werner Liemberger, Christian Achatz
    * @version
    * Version 0.1, 21.2.2011<br />
    * Version 0.2, 03.08.2016 (ID#303: allow hiding via template definition)<br />
    */
   public function onParseTime() {

      $this->initHoursRange();
      $this->initOffsetNames();

      if (!empty($this->attributes['minutesinterval'])) {
         $this->minutesInterval = (int) $this->attributes['minutesinterval'];
      }
      if (!empty($this->attributes['showseconds']) && $this->attributes['showseconds'] == "false") {
         $this->showSeconds = false;
      }

      // create select boxes then apply context and language
      $hours = new SelectBoxTag();
      $hours->setLanguage($this->language);
      $hours->setContext($this->context);

      $minutes = new SelectBoxTag();
      $minutes->setLanguage($this->language);
      $minutes->setContext($this->context);

      $seconds = null;
      if ($this->showSeconds != false) {
         $seconds = new SelectBoxTag();
         $seconds->setLanguage($this->language);
         $seconds->setContext($this->context);
      }

      $name = $this->getAttribute('name');

      // apply field names and calculate id to be able access
      // the child elements using JS
      $hoursIdent = $name . '[' . $this->offsetNames['Hours'] . ']';
      $hours->setAttribute('name', $hoursIdent);
      $hours->setAttribute('id', $hoursIdent);

      $minutesIdent = $name . '[' . $this->offsetNames['Minutes'] . ']';
      $minutes->setAttribute('name', $minutesIdent);
      $minutes->setAttribute('id', $minutesIdent);

      if ($this->showSeconds != false) {
         $secondsIdent = $name . '[' . $this->offsetNames['Seconds'] . ']';
         $seconds->setAttribute('name', $secondsIdent);
         $seconds->setAttribute('id', $secondsIdent);
      }


      // set the values for the hours select box
      for ($i = (int) $this->hoursRange['Start']; $i <= (int) $this->hoursRange['End']; $i++) {
         $i = $this->appendZero($i);
         $hours->addOption($i, $i);
      }

      // set the values for the minutes select box
      $i = 0;
      while ($i < 60) {
         $i = $this->appendZero($i);
         $minutes->addOption($i, $i);
         $i += $this->minutesInterval;
      }


      // set the values for the seconds select box
      if ($this->showSeconds != false) {
         for ($i = 0; $i < 60; $i++) {
            $i = $this->appendZero($i);
            $seconds->addOption($i, $i);
         }
      }

      // preset today's time on startup
      if ($this->getRequest()->getParameter($name) === null) {
         $hours->setOption2Selected($this->appendZero(date('G')));
         $minutes->setOption2Selected($this->appendZero(date('i')));
         if ($this->showSeconds != false) {
            $seconds->setOption2Selected(date('s'));
         }
      }

      // execute the on parse time (important for presetting!)
      $hours->onParseTime();
      $minutes->onParseTime();
      if ($this->showSeconds != false) {
         $seconds->onParseTime();
      }

      // reference the father object and add to the children list
      $hours->setParentObject($this);
      $minutes->setParentObject($this);
      if ($this->showSeconds != false) {
         $seconds->setParentObject($this);
      }
      $this->children['h'] = $hours;
      $this->children['m'] = $minutes;
      if ($this->showSeconds != false) {
         $this->children['s'] = $seconds;
      }

      // execute onAfterAppend() to ensure native APF environment
      $this->children['h']->onAfterAppend();
      $this->children['m']->onAfterAppend();
      if ($this->showSeconds != false) {
         $this->children['s']->onAfterAppend();
      }

      // ID#303: allow to hide form element by default within a template
      if ($this->getAttribute('hidden', 'false') === 'true') {
         $this->hide();
      }
   }

   /**
    * Initializes the hours range to display.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   protected function initHoursRange() {

      // read the range for the hours select box
      if ($this->hasAttribute('hoursrange')) {

         $hoursRange = explode('-', $this->getAttribute('hoursrange'));

         if (count($hoursRange) == 2) {
            $this->hoursRange['Start'] = trim($this->appendZero($hoursRange[0]));
            $this->hoursRange['End'] = trim($this->appendZero($hoursRange[1]));
         }
      }
   }

   /**
    * Appends a zero for hours, minutes or seconds numbers without leading zeros.
    *
    * @param int $input The hour, minute or second number.
    *
    * @return string Hour, minute or second number with leading zero.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   protected function appendZero($input) {
      return sprintf('%02s', $input);
   }

   /**
    * Initializes the offset names of the fields.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   protected function initOffsetNames() {

      if ($this->hasAttribute('offsetnames')) {

         $offsetNames = explode(';', $this->getAttribute('offsetnames'));

         if (count($offsetNames) == 3) {
            $this->offsetNames = [
                  'Hours'   => $offsetNames[0],
                  'Minutes' => $offsetNames[1],
                  'Seconds' => $offsetNames[2]
            ];
         }
         if (count($offsetNames) == 2) {
            $this->offsetNames = [
                  'Hours'   => $offsetNames[0],
                  'Minutes' => $offsetNames[1]
            ];
         }
      }
   }

   /**
    * Generated the HTML code of the time control.
    *
    * @return string The HTML code of the time control.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   public function transform() {

      if (!$this->isVisible) {
         return '';
      }

      // as of 1.12, the time control should be rendered using a
      // surrounding span do enable the client validator extension
      // to address the control more easily.
      $buffer = (string) '<span id="' . $this->getId() . '"';

      $style = $this->getAttribute('style');
      if ($style != null) {
         $buffer .= ' style="' . $style . '"';
      }

      $class = $this->getAttribute('class');
      if ($class != null) {
         $buffer .= ' class="' . $class . '"';
      }
      $buffer .= '>';
      foreach ($this->children as $section => $DUMMY) {
         $buffer .= $this->children[$section]->transform();
      }

      return $buffer . '</span>';
   }

   /**
    * Returns the id of the time element. In case the developer does not
    * provide the <em>id</em> attribute within the tag definition, the
    * <em>name</em> attribute is used instead.
    *
    * @return string The id to display within the HTML tag.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   private function getId() {
      $id = $this->getAttribute('id');
      if ($id === null) {
         return $this->getAttribute('name');
      }

      return $id;
   }

   /**
    * Re-implements the retrieving of values for time controls
    *
    * @return DateTime The current value of the control.
    *
    * @since 1.14
    *
    * @author Ralf Schubert
    * @version
    * Version 0.1, 26.07.2011<br />
    */
   public function getValue() {
      return $this->getTime();
   }

   /**
    * Returns the time with the pattern HH:MM:SS or if ShowSeconds is false with HH:MM
    *
    * @return DateTime DateTime instance with pattern HH:MM:SS or if showSeconds is false with HH:MM.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   public function getTime() {

      $hours = $this->getHoursControl()->getSelectedOption();
      $minutes = $this->getMinutesControl()->getSelectedOption();

      if ($this->showSeconds === false) {

         // in case any of the select boxes are missing a none-empty selection, the time is null
         if ($hours === null || $minutes === null) {
            return null;
         }

         return DateTime::createFromFormat('H:i', $hours->getValue() . ':' . $minutes->getValue());
      } else {
         $seconds = $this->getSecondsControl()->getSelectedOption();

         // in case any of the select boxes are missing a none-empty selection, the time is null
         if ($hours === null || $minutes === null || $seconds === null) {
            return null;
         }

         return DateTime::createFromFormat('H:i:s', $hours->getValue() . ':' . $minutes->getValue() . ':' . $seconds->getValue());
      }
   }

   /**
    * Returns a reference on the hours control of the time control.
    *
    * @return SelectBoxTag The hours control.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   public function &getHoursControl() {
      return $this->children['h'];
   }

   /**
    * Returns a reference on the minutes control of the time control.
    *
    * @return SelectBoxTag The minutes control.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   public function &getMinutesControl() {
      return $this->children['m'];
   }

   /**
    * Returns a reference on the seconds control of the date control.
    *
    * @return SelectBoxTag The seconds control.
    *
    * @author Werner Liemberger
    * @version
    * Version 0.1, 21.2.2011<br />
    */
   public function &getSecondsControl() {
      return $this->children['s'];
   }

   /**
    * Re-implements the setting of values for time controls
    *
    * @param DateTime|string $value The time value to set.
    *
    * @return $this This instance for further usage.
    *
    * @since 1.14
    *
    * @author Ralf Schubert
    * @version
    * Version 0.1, 26.07.2011<br />
    */
   public function &setValue($value) {
      $this->setTime($value);

      return $this;
   }

   /**
    * Allows you to initialize the time control with a given time (e.g. "08:31" or "08:31:20" or "2011-02-21 08:31:00").
    *
    * @param string $time The time to initialize the control with.
    *
    * @throws FormException In case of date parsing errors.
    *
    * @author Christian Achatz
    * @version
    * Version 0.1, 16.06.2010<br />
    */
   public function setTime($time) {

      if (!($time instanceof DateTime)) {
         if ($this->showSeconds === false) {
            $time = DateTime::createFromFormat('H:i', $time);
         } else {
            $time = DateTime::createFromFormat('H:i:s', $time);
         }
      }

      $this->getHoursControl()->setOption2Selected($this->appendZero($time->format('H')));
      $this->getMinutesControl()->setOption2Selected($this->appendZero($time->format('i')));
      if ($this->showSeconds !== false) {
         $this->getSecondsControl()->setOption2Selected($time->format('s'));
      }
   }

   public function reset() {
      // rely on SelectBoxTag::reset()...
      $this->getHoursControl()->reset();
      $this->getMinutesControl()->reset();
      if ($this->showSeconds !== false) {
         $this->getSecondsControl()->reset();
      }
   }

}
