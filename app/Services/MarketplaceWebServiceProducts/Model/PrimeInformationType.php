<?php
/*******************************************************************************
 * Copyright 2009-2018 Amazon Services. All Rights Reserved.
 * Licensed under the Apache License, Version 2.0 (the "License"); 
 *
 * You may not use this file except in compliance with the License. 
 * You may obtain a copy of the License at: http://aws.amazon.com/apache2.0
 * This file is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR 
 * CONDITIONS OF ANY KIND, either express or implied. See the License for the 
 * specific language governing permissions and limitations under the License.
 *******************************************************************************
 * PHP Version 5
 * @category Amazon
 * @package  Marketplace Web Service Products
 * @version  2011-10-01
 * Library Version: 2017-03-22
 * Generated: Thu Oct 11 10:46:02 PDT 2018
 */

/**
 *  @see MarketplaceWebServiceProducts_Model
 */

require_once (dirname(__FILE__) . '/../Model.php');


/**
 * MarketplaceWebServiceProducts_Model_ShipsFromType
 * 
 * Properties:
 * <ul>
 * 
 * <li>State: string</li>
 * <li>Country: string</li>
 *
 * </ul>
 */

 class MarketplaceWebServiceProducts_Model_PrimeInformationType extends MarketplaceWebServiceProducts_Model {

    public function __construct($data = null)
    {
    $this->_fields = array (
    'IsPrime' => array('FieldValue' => null, 'FieldType' => 'bool'),
    'IsNationalPrime' => array('FieldValue' => null, 'FieldType' => 'bool'),
    );
    parent::__construct($data);
    }

}
