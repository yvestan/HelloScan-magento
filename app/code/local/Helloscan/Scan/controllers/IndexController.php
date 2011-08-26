<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 ff=unix fenc=utf8: */

/**
*
* HelloScan for Magento
*
* @package HelloScan_Magento
* @author Yves Tannier [grafactory.net]
* @copyright 2011 Yves Tannier
* @link http://helloscan.mobi
* @version 0.1
* @license MIT Licence
*/

// debug ?
define('HELLOSCAN_DEBUG', false);

class Helloscan_Scan_IndexController extends Mage_Core_Controller_Front_Action
{

    // object
    public $HS_responseHandler;
    public $HS_check;

    /**
     * Common
     *
     * @return array
     */
    public function initHelloScan() {

        // user parameters
        $HS_requestParams = new HelloScan_RequestParams();

        // response handler
        $this->HS_responseHandler = new HelloScan_ResponseHandler();

        // check key
        $HS_authKey = new HelloScan_AuthKey($HS_requestParams);

        if(!$HS_authKey->check()) {
            // send response and exit
            $this->HS_responseHandler->sendResponse(array(
                'status' => '401',
                'response' => 'Bad authorisation key'
            ));
        }

        // check product code
        if(!$HS_requestParams->codeExist()) {
            // send response and exit
            $this->HS_responseHandler->sendResponse(array(
                'status' => '404',
                'response' => 'Product code unvalaible'
            ));
        }

        // helloscan
        $this->HS_check = new HelloScan_Check($HS_requestParams);

    }

    /**
     * Gets products infos
     *
     * @return array
     */
    public function getAction() {
        $this->initHelloScan();
        $HS_actionResult = $this->HS_check->get();
        $this->HS_responseHandler->sendResponse($HS_actionResult);
    }

    /**
     * Add product inventory
     *
     * @return array
     */
    public function addAction() {
        $this->initHelloScan();
        $HS_actionResult = $this->HS_check->add();
        $this->HS_responseHandler->sendResponse($HS_actionResult);
    }

    /**
     * remove product inventory
     *
     * @return array
     */
    public function removeAction() {
        $this->initHelloScan();
        $HS_actionResult = $this->HS_check->remove();
        $this->HS_responseHandler->sendResponse($HS_actionResult);
    }

}

// get params from helloscan request
class HelloScan_RequestParams {

    // code from scan result
    public $code = null;

    // action from app
    public $action = null;

    // action from app
    public $authkey = null;

    // possible actions
    protected $actions = array(
        'get',
        'add',
        'remove',
    );

    // {{{ getCode()

    /** get code : ean13, reference or id_product
     *
     */
    public function getCode() {
        if(!empty($_GET['code'])) {
            return $this->code = htmlspecialchars($_GET['code']);
        }
        return false;
    }

    // }}}

    // {{{ codeExist()

    /** check product code
     *
     */
    public function codeExist() {
        if(!$this->getCode()) {
            return false;
        }
        return true;
    }

    // }}}

    // {{{ getAction()

    /** get action (check, add, remove...)
     *
     */
    public function getAction() {
        if(!empty($_GET['action']) && in_array($_GET['action'], $this->actions)) {
            return $this->action = htmlspecialchars($_GET['action']); 
        }
        return false;
    }

    // }}}

    // {{{ getAuthKey()

    /** get authentification key
     *
     */
    public function getAuthKey() {
        if(!empty($_GET['authkey'])) {
            return $this->authkey = htmlspecialchars($_GET['authkey']); 
        }
        return false;
    }

    // }}}

}

// check autorisation key
class HelloScan_AuthKey {

    // request params
    protected $params = null;

    // auth_key from magento config
    protected $magento_auth_key;

    // {{{ __construct()

    /** constructeur
     *
     * @param object $params request parameters
     */
    public function __construct($params) {
        $this->params = $params;

        // get key from magento config
        $this->magento_auth_key = (string)Mage::getStoreConfig('scan/settings/auth_key');

    }

    // }}}

    // {{{ check()

    /** auth key / compare with saved authkey
     *
     */
    public function check() {
        if(!empty($this->magento_auth_key) && $this->params->getAuthKey() 
            && $this->params->getAuthKey()==$this->magento_auth_key) {
            $this->authkey = $this->params->getAuthKey();
            return true;
        }
        return false;
    }

    // }}}

}
   
// check product code and perform actions
class HelloScan_Check {

    // request params
    protected $params = null;
       
    // debug mode
    private $debug = HELLOSCAN_DEBUG;

    // {{{ __construct()

    /** constructeur
     *
     * @param object $params request parameters
     */
    public function __construct($params) {
        $this->params = $params;
    }

    // }}}

    // {{{ checkProductByCode()

    /** check if code is associate with product
     *
     */
    public function checkProductByCode() {
        $product = Mage::helper('catalog/product')->getProduct($this->params->getCode());
        if(!empty($product)) {
            $product->entity_id = $product->entity_id;
            return $product;
        } else {
            return false;
        }
    }

    // }}}

    // {{{ get()

    /** get product infos from code
     *
     * @return array
     */
    public function get() {
        if($product = $this->checkProductByCode()) {
            $product_tabs = array(
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'sku' => $product->getSku(),
                'inventory' => round($product->getStockItem()->qty),
            );
            return array(
                'status' => 200,
                'result' => 'Product informations',
                'data' => $product_tabs,
            );
        } else {
           return array(
                'status' => 404,
                'result' => 'No product found',
           );
        }
    }

    // }}}

    // {{{ add()

    /** add 1 product from stock
     *
     * @return array
     */
    public function add() {
        return $this->update('add');
    }

    // }}}

    // {{{ remove()

    /** remove 1 product from stock
     *
     * @return array
     */
    public function remove() {
        return $this->update('remove');
    }

    // }}}

    // {{{ update()

    /** remove or add product
     *
     * @return array
     */
    public function update($action) {
        if($product = $this->checkProductByCode()) {
            $stockData = $product->getStockItem();
            if($action=='remove') {
                $stockData['qty'] = $stockData['qty']-1;
            } else {
                $stockData['qty'] = $stockData['qty']+1;
            }
            $product->setStockData($stockData);
            try {
                $product->save();
                return array(
                    'status' => '200',
                    'result' => 'Quantity updated: '.$action.' ('.$stockData['qty'].')'
                );
            }
            catch (Exception $ex) {
                return array(
                    'status' => '500',
                    'result' => 'Error during quantity '.$action.' ('.$stockData['qty'].')'
                );
                //echo $ex->getMessage();
                //$this->setDebug('add SQL', $sql);
            }
        } else {
           return array(
                'status' => 404,
                'result' => 'No product found to '.$action.' quantity',
           );
        }
    }

    // }}}

    // {{{ setDebug()

    /** debug
     *
     * @param string $key Key
     * @param string $value Value debug
     */
    public function setDebug($key,$value) {
        if($this->debug) {
            echo $key.' : '.$value;
        }
    }

}

// response format and send
class HelloScan_ResponseHandler {

    // {{{ sendResponse()

    /** response
     *
     */
    public function sendResponse($response,$format='json') {
        if($format=='json') {
            //header('Content-Type: application/json'); 
            echo json_encode($response);
        }
        exit;
    }

    // }}}

}
