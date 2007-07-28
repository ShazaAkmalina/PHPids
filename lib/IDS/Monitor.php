<?php

/**
 * PHP IDS
 *
 * Requirements: PHP5, SimpleXML
 *
 * Copyright (c) 2007 PHPIDS (http://php-ids.org)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the license.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * Introdusion Dectection System
 *
 * This class provides function(s) to scan incoming data for
 * malicious script fragments
 *
 * @author		.mario <mario.heiderich@gmail.com>
 * @author		christ1an <ch0012@gmail.com>
 * @author		Lars Strojny <lstrojny@neu.de>
 *
 * @version		$Id$
 */
class IDS_Monitor {

	private $tags	 = NULL;
	private $request = NULL;
	private $storage = NULL;

	private $report;
	
	public	$scanKeys = false;

	/**
	 * This array is meant to define which variables need to be ignored
	 * by the PHPIDS - default is the utmz google analytics parameter
	 */
	private $exceptions = array(
		'__utmz'
	);

	/**
	 * Constructor
	 *
	 * @param	array	$reqeust			Request array
	 * @param	object  IDS_Filter_Storage	Filter storage object
	 * @param	tags	optional			List of tags where filters should be applied
	 * @return 	void
	 */
	public function __construct(Array $request, IDS_Filter_Storage $storage, Array $tags = NULL) {
		if (!empty($request)) {
			$this->storage  = $storage;
			$this->request  = $request;
			$this->tags	 	= $tags;
		}

		require_once 'IDS/Report.php';
		$this->report = new IDS_Report;
	}

	/**
	 * Starts the detection mechanism
	 *
	 * @return	object	IDS_Report
	 */
	public function run() {
		if (!empty($this->request)) {
			foreach ($this->request as $key => $value) {
				$this->iterate($key, $value);
			}
		}

		return $this->getReport();
	}

	/**
	 * Iterates through given data and delegates it
	 * to IDS_Monitor::detect() in order to check for malicious
	 * appearing fragments
	 *
	 * @access	private
	 * @param	mixed   $key
	 * @param	mixed   $value
	 * @return 	void
	 */
	private function iterate($key, $value) {
		if (!is_array($value)) {

        	if ($filter = $this->detect($key, $value)) {
				require_once 'IDS/Event.php';
				$this->report->addEvent(
					new IDS_Event(
						$key,
						$value,
						$filter
					)
				);
			}
		} else {
			foreach ($value as $subKey => $subValue) {
				$this->iterate(
					$key . '.' . $subKey, $subValue
				);
			}
		}
	}

	/**
	 * Checks whether given value matches any of the supplied
	 * filter patterns
	 *
	 * @access	private
	 * @param	mixed	$key
	 * @param	mixed	$value
	 * @return	mixed   false or array of filter(s) that matched the value
	 */
	private function detect($key, $value) {
		
        # only start detection if value isn't alphanumeric
        if (preg_match('/[^\w\s\/]+/ims', $value) && !empty($value)) {
            
			if (in_array($key, $this->exceptions, true)) {
				return false;
			}

            # require and use the converter
            require_once 'IDS/Converter.php';
            $value	= IDS_Converter::runAll($value);
			$value	= get_magic_quotes_gpc() 	? stripslashes($value) 			: $value;
			$key 	= $this->scanKeys 			? IDS_Converter::runAll($key) 	: $key;

			$filters	= array();
			$filterSet	= $this->storage->getFilterSet();
			foreach ($filterSet as $filter) {

				# In case we have a tag array specified the IDS will only
				# use those filters that are meant to detect any of the given tags
				if (is_array($this->tags)) {
					if (array_intersect($this->tags, $filter->getTags())) {
						if ($this->match($key, $value, $filter)) {
							$filters[] = $filter;
						}
					}

				# We make use of all filters available
				} else {
					if ($this->match($key, $value, $filter)) {
						$filters[] = $filter;
					}
				}
			}
					
			return empty($filters) ? false : $filters;
		}
	}
	
	/**
	 * Matches given value and/or key against given filter
	 *
	 * @param	mixed	$key
	 * @param	mixed	$value
	 * @param	object	$filter
	 * @return	boolean
	 */
	private function match($key, $value, $filter) {
		if ($this->scanKeys) {
			if ($filter->match($key)) {
				return true;
			}
		}

		if ($filter->match($value)) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Sets exception array
	 *
	 * @param	mixed	$exceptions list of fields names that should be ignored
	 * @return	void
	 */
	public function setExceptions($exceptions) {
		if (!is_array($exceptions)) {
			$exceptions = array($exceptions);
		}
		
		$this->exceptions = $exceptions;
	}

	/**
	 * Returns exception array
	 *
	 * @return	array
	 */
	public function getExceptions() {
		return $this->exceptions;
	}

	/**
	 * Returns report object providing various functions to
	 * work with detected results
	 *
	 * @return	object	IDS_Report
	 */
	public function getReport() {
		return $this->report;
	}

}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 */