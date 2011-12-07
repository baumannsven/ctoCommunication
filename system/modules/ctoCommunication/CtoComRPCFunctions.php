<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  MEN AT WORK 2011
 * @package    ctoCommunication
 * @license    GNU/LGPL
 * @filesource
 */

/**
 * Remote Procedure Call Class
 */
class CtoComRPCFunctions extends Backend
{
    /* -------------------------------------------------------------------------
     * Vars
     */

    //- Singelten pattern --------
    protected static $instance = null;

    /* -------------------------------------------------------------------------
     * Core
     */

    /**
     * Construtor
     */
    protected function __construct()
    {
        parent::__construct();

        $this->import("Config");
    }

    /**
     * Singelten pattern
     * 
     * @return CtoComRPCFunctions 
     */
    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new CtoComRPCFunctions();

        return self::$instance;
    }

    /* -------------------------------------------------------------------------
     * RPC Functions
     */
    
    //- File Functions --------
    
    public function getResponsePart($strFilename, $intFilecount)
    {
        $strFilepath = "/system/tmp/" . $intFilecount . "_" . $strFilename;

        if (!file_exists(TL_ROOT . $strFilepath))
        {
            throw new Exception("Missing partfile $strFilepath");
        }

        $objFile = new File($strFilepath);
        $strReturn = $objFile->getContent();
        $objFile->close();

        return $strReturn;
    }

    //- Referer Functions --------

    /**
     * Disable referer check from contao
     * 
     * @return boolean 
     */
    public function referrer_disable()
    {
        $this->Config->update("\$GLOBALS['TL_CONFIG']['disableRefererCheck']", true);
        return true;
    }

    /**
     * Enable referer check from contao
     * 
     * @return boolean 
     */
    public function referrer_enable()
    {
        $this->Config->update("\$GLOBALS['TL_CONFIG']['disableRefererCheck']", false);
        return false;
    }

    //- Version Functions --------

    public function getCtoComVersion()
    {
        return $GLOBALS["CTOCOM_VERSION"];
    }
    
    public function getContaoVersion()
    {
        return VERSION;
    }

}

?>
