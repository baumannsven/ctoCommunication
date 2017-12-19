<?php
/**
 * Created by PhpStorm.
 * User: stefan.heimes
 * Date: 08.01.2016
 * Time: 17:08
 */

namespace MenAtWork\CtoCommunicationBundle\Controller;

use MenAtWork\CtoCommunicationBundle\Container\ClientState;
use MenAtWork\CtoCommunicationBundle\Container\Error;
use MenAtWork\CtoCommunicationBundle\Container\IO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Client extends Base
{

    const HTTP_CODE_OK = 200;
    const HTTP_CODE_FORBIDDEN = 403;
    const HTTP_CODE_NOT_FOUND = 404;
    const HTTP_CODE_FAILED_DEPENDENCY = 424;

    /**
     * Star for routing.
     */
    public function execute(Request $request)
    {
        // Init the state container.
        $clientState = new ClientState();
        $clientState
            ->setRequest($request)
            ->setConTimeout(1200);

        // Check ping.
        if ($clientState->isPingRequest()) {
            return $this->handlePing();
        }

        // Check the crypt engines.
        if (!$clientState->setupCrypt()) {
            return $this->handleRespone(self::HTTP_CODE_FORBIDDEN);
        }

        //Now the key for the crypt class.
        if ($clientState->isHandshakeRequest()) {
            $passwordState = $clientState->setupCryptPassword($clientState::CRYPT_PASSWORD_API_KEY);
        } else {
            $passwordState = $clientState->setupCryptPassword($clientState::CRYPT_PASSWORD_EXCHANGE);
        }

        // If the password was not set end here.
        if (!$passwordState) {
            return $this->handleRespone(self::HTTP_CODE_FAILED_DEPENDENCY);
        }

        // Default handling.
        return $this->handle404();
    }

    /**
     * Just handle a ping request.
     *
     * @return Response
     */
    private function handlePing()
    {
        return new Response('', self::HTTP_CODE_OK);
    }

    /**
     * Send the 404 header.
     *
     * @return Response
     */
    private function handle404()
    {
        return new Response('', self::HTTP_CODE_NOT_FOUND);
    }

    /**
     * Default handling function.
     *
     * @param int $statusCode The http header code.
     *
     * @return Response
     */
    private function handleRespone($statusCode)
    {
        return new Response('', $statusCode);
    }

    /**
     * Run the communication as client
     *
     * @return void
     */
    public function run()
    {

        /* ---------------------------------------------------------------------
         * Check the API key.
         * Check if the API Key was send.
         * Check if the API key contains the RPC Call and the API Key from this
         * Contao Version.
         */

        // Check if a API-Key was send
        if (strlen(\Input::get("apikey")) == 0) {
            \System::log(vsprintf("Call from %s without a API Key.", \Environment::get('ip')),
                __FUNCTION__ . " | " . __CLASS__, TL_ERROR);

            // Clean output buffer
            while (@ob_end_clean()) {
                ;
            }
            exit();
        }

        // Check RPC Call from get and the RPC Call from API-Key
        $mixVar    = $this->objCodifyengineBasic->Decrypt(base64_decode(\Input::get("apikey", true)));
        $mixVar    = trimsplit("@\|@", $mixVar);
        $strApiKey = $mixVar[1];
        $strAction = $mixVar[0];

        if ($strAction != \Input::get("act")) {
            \System::log(vsprintf("Error Api Key from %s. Request action: %s | Key action: %s | Api: %s", array(
                \Environment::get('ip'),
                \Input::get("act"),
                $strAction,
                $strApiKey
            )), __FUNCTION__ . " | " . __CLASS__, TL_ERROR);

            // Clean output buffer
            while (@ob_end_clean()) {
                ;
            }
            exit();
        }

        if ($GLOBALS['TL_CONFIG']['ctoCom_APIKey'] != $strApiKey) {
            \System::log(vsprintf("Call from %s with a wrong API Key: %s",
                array(\Environment::get('ip'), \Input::get("apikey"))), __FUNCTION__ . " | " . __CLASS__,
                TL_ERROR);

            // Clean output buffer
            while (@ob_end_clean()) {
                ;
            }
            exit();
        }

        /* ---------------------------------------------------------------------
         * Check language settings
         */

        if (empty($GLOBALS['TL_LANGUAGE'])) {
            $GLOBALS['TL_LANGUAGE'] = "en";
        }

        /* ---------------------------------------------------------------------
         * Set I/O System
         */

        if (strlen(\Input::get("format")) != 0) {
            if (\MenAtWork\CtoCommunicationBundle\InputOutput\Factory::engineExist(\Input::get("format"))) {
                $this->setIOEngine(\Input::get("format"));
            } else {
                $this->setIOEngine();

                $this->objError = new Error();
                $this->objError->setLanguage("unknown_io");
                $this->objError->setID(10);
                $this->objError->setObject("");
                $this->objError->setMessage("No I/O Interface found for accept.");
                $this->objError->setRPC("");
                $this->objError->setClass("");
                $this->objError->setFunction("");

                $this->generateOutput();
                exit();
            }
        } else {
            $strAccept = $_SERVER['HTTP_ACCEPT'];
            $strAccept = preg_replace("/;q=\d\.\d/", "", $strAccept);
            $arrAccept = trimsplit(",", $strAccept);

            $strIOEngine = false;

            foreach ($arrAccept as $key => $value) {
                $strIOEngine = \MenAtWork\CtoCommunicationBundle\InputOutput\Factory::getEngingenameForAccept($value);

                if ($strIOEngine !== false) {
                    break;
                }
            }

            if ($strIOEngine === false) {
                $this->objIOEngine = \MenAtWork\CtoCommunicationBundle\InputOutput\Factory::getEngine('default');

                $this->objError = new Error();
                $this->objError->setLanguage("unknown_io");
                $this->objError->setID(10);
                $this->objError->setObject("");
                $this->objError->setMessage(sprintf("No I/O Interface found for accept: %s",
                    var_export($strAccept, true)));
                $this->objError->setRPC("");
                $this->objError->setClass("");
                $this->objError->setFunction("");

                $this->generateOutput();
                exit();
            } else {
                $this->setIOEngine($strIOEngine);
            }
        }

        /* ---------------------------------------------------------------------
         * Run RPC-Check function
         */

        // Check if act is set
        $mixRPCCall = \Input::get("act");

        if (strlen($mixRPCCall) == 0) {
            $this->objError = new Error();
            $this->objError->setLanguage("rpc_missing");
            $this->objError->setID(1);
            $this->objError->setObject("");
            $this->objError->setMessage("Missing RPC Call");
            $this->objError->setRPC($mixRPCCall);
            $this->objError->setClass("");
            $this->objError->setFunction("");

            $this->generateOutput();
            exit();
        }

        if (!array_key_exists($mixRPCCall, $this->arrRpcList)) {
            $this->objError = new Error();
            $this->objError->setLanguage("rpc_unknown");
            $this->objError->setID(1);
            $this->objError->setObject("");
            $this->objError->setMessage("Unknown RPC Call");
            $this->objError->setRPC($mixRPCCall);
            $this->objError->setClass("");
            $this->objError->setFunction("");

            $this->generateOutput();
            exit();
        }

        /* ---------------------------------------------------------------------
         * Build a list with parameter from the POST
         */

        $arrParameter = array();

        if ($this->arrRpcList[$mixRPCCall]["parameter"] != false && is_array($this->arrRpcList[$mixRPCCall]["parameter"])) {
            switch ($this->arrRpcList[$mixRPCCall]["typ"]) {
                // Decode post
                case "POST":
                    // Decode each post
                    $arrPostValues = array();
                    foreach ($_POST as $key => $value) {
                        if ((version_compare('3.2.16', VERSION . '.' . BUILD, '<=') && version_compare('3.3.0',
                                    VERSION . '.' . BUILD, '>'))
                            || version_compare('3.3.7', VERSION . '.' . BUILD, '<=')
                        ) {
                            // Get the raw data.
                            $mixPost = \Input::postUnsafeRaw($key);
                        } else {
                            // Get the raw data for older contao versions.
                            $mixPost = \Input::postRaw($key);
                        }

                        $mixPost             = $this->objIOEngine->InputPost($mixPost, $this->objCodifyengine);
                        $arrPostValues[$key] = $mixPost;

                        \Input::setPost($key, $mixPost);
                    }

                    // Check if all post are set
                    foreach ($this->arrRpcList[$mixRPCCall]["parameter"] as $value) {
                        $arrPostKey = array_keys($arrPostValues);

                        if (!in_array($value, $arrPostKey)) {
                            $arrParameter[$value] = null;
                        } else {
                            // Get the raw data.
                            $arrParameter[$value] = $arrPostValues[$value];
                        }
                    }

                    unset($arrPostValues);
                    break;

                default:
                    break;
            }
        }

        /* ---------------------------------------------------------------------
         * Call function
         */

        try {
            $strClassname = $this->arrRpcList[$mixRPCCall]["class"];

            if (!class_exists($strClassname)) {
                $this->objError = new Error();
                $this->objError->setLanguage("rpc_class_not_exists");
                $this->objError->setID(4);
                $this->objError->setObject($value);
                $this->objError->setMessage("The choosen class didn`t exists.");
                $this->objError->setRPC($mixRPCCall);
                $this->objError->setClass($this->arrRpcList[$mixRPCCall]["class"]);
                $this->objError->setFunction($this->arrRpcList[$mixRPCCall]["function"]);

                $this->generateOutput();
                exit();
            }

            $objReflection = new \ReflectionClass($strClassname);

            if ($objReflection->hasMethod("getInstance")) {
                $object          = call_user_func_array(array($this->arrRpcList[$mixRPCCall]["class"], "getInstance"),
                    array());
                $this->mixOutput = call_user_func_array(array($object, $this->arrRpcList[$mixRPCCall]["function"]),
                    $arrParameter);
            } else {
                $object = new $this->arrRpcList[$mixRPCCall]['class'];
                $this->mixOutput = call_user_func_array(array($object, $this->arrRpcList[$mixRPCCall]["function"]),
                    $arrParameter);
            }
        } catch (\Exception $exc) {
            $this->objError = new Error();
            $this->objError->setLanguage("rpc_unknown_exception");
            $this->objError->setID(3);
            $this->objError->setObject("");
            $this->objError->setMessage($exc->getMessage());
            $this->objError->setRPC($mixRPCCall);
            $this->objError->setClass($this->arrRpcList[$mixRPCCall]["class"]);
            $this->objError->setFunction($this->arrRpcList[$mixRPCCall]["function"]);
            $this->objError->setException($exc);

            \System::log(vsprintf("RPC Exception: %s | %s", array($exc->getMessage(), nl2br($exc->getTraceAsString()))),
                __CLASS__ . " | " . __FUNCTION__, TL_ERROR);

            $this->generateOutput();
            exit();
        }

        $this->generateOutput();
        exit();
    }

    /**
     * Build the answer and serialize it
     *
     * @return string
     */
    protected function generateOutput()
    {
        $objOutputContainer = new IO();

        if ($this->objError == false) {
            $objOutputContainer->setSuccess(true);
            $objOutputContainer->setResponse($this->mixOutput);
            $objOutputContainer->setSplitcontent(false);
            $objOutputContainer->setSplitcount(0);
            $objOutputContainer->setSplitname("");
        } else {
            $objOutputContainer->setSuccess(false);
            $objOutputContainer->setError($this->objError);
            $objOutputContainer->setResponse(null);
            $objOutputContainer->setSplitcontent(false);
            $objOutputContainer->setSplitcount(0);
            $objOutputContainer->setSplitname("");
        }

        $mixOutput = $this->objIOEngine->OutputResponse($objOutputContainer, $this->objCodifyengine);

        // Check if we have a big output and split it
        if ($this->config->getResponseLength() != -1 && strlen($mixOutput) > $this->config->getResponseLength()) {
            $mixOutput    = str_split($mixOutput, (int)($this->config->getResponseLength() * 0.8));
            $strFileName  = md5(time()) . md5(rand(0, 65000)) . ".ctoComPart";
            $intCountPart = count($mixOutput);

            foreach ($mixOutput as $keyOutput => $valueOutput) {
                $objFile = new \File("system/tmp/" . $keyOutput . "_" . $strFileName);
                $objFile->write($valueOutput);
                $objFile->close();
            }

            $objOutputContainer = new IO();
            $objOutputContainer->setSuccess(true);
            $objOutputContainer->setResponse(null);
            $objOutputContainer->setSplitcontent(true);
            $objOutputContainer->setSplitcount($intCountPart);
            $objOutputContainer->setSplitname($strFileName);

            $mixOutput = $this->objIOEngine->OutputResponse($objOutputContainer, $this->objCodifyengine);
        }

        // Set some header fields
        header("Content-Type: " . $GLOBALS["CTOCOM_IO"][$this->strIOEngine]["contentType"]);

        // Clean output buffer
        while (@ob_end_clean()) {
            ;
        }

        // Echo response
        echo($mixOutput);
    }
}
