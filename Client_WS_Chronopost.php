<?php
/**
 * Created by PhpStorm.
 * User: NUlmann
 * Date: 18/12/2018
 * Time: 15:40
 */

class Client_WS_Chronopost
{
    /**
     * @var string Fichier de log
     */
    protected $fichier_log;

    /**
     * @var boolean Logs activés (true/false)
     *                false si $fichier_log est vide
     */
    protected $log;

    /**
     * @var boolean Mode debug avctivé ? (true/false)
     */
    protected $debug;

    /**
     * @var SoapClient_Custom Client pour les appels WS
     */
    protected $client;

    /**
     * @var string Destinataire de l'expédition
     */
    protected $destinataire;


    //Définition des constantes d'erreur
    const ERR_FATAL = 0;
    const ERR_SOAP = 1;
    const ERR_SQL = 2;
    const ERR_NO_DATA = 3;
    const ERR_REJET = 4;


    /**
     * Constructeur
     *
     * @param string $fichier_log URI du fichier de log
     * @param boolean $debug Mode debug? (true/false)
     */
    public function __construct($fichier_log = null, $debug = true)
    {

        $this->fichier_log = $fichier_log;
        $this->debug       = $debug;
        $this->log         = empty($fichier_log) ? false : true;
    }


    public function connect()
    {

        //Désactive le cache
        ini_set('soap.wsdl_cache_enabled', '0');


        if ($this->debug)
            echo "\t#connect\n";
        if ($this->log)
            Log::ecrireLog('#connect', $this->fichier_log);


        //Ne fait rien si le SoapClient est déjà instancié
        if (isset($this->client) && is_object($this->client)) {
            return true;
        } //Instancie le client si pas déjà fait
        else {
            try {
                //Instancie le client SOAP
                $this->client = new SoapClient_Custom(
                    WS_CHRONOPOST_SHIPPING_URL,
                    array(
                        'trace' => 1,
                        'soap_version' => SOAP_1_1,
                        'encoding' => 'UTF-8'
                    )
                );


            } catch (Exception $e) {
                $origine       = 'Client_WS_Chronopost::connect';
                $code_erreur   = self::ERR_FATAL;
                $parametres    = 'wsdl:' . $this->wsdl;
                $message       = $e->getMessage();
                $trace         = $e->getTraceAsString();
                $last_request  = null;
                $last_response = null;
                return self::chronopost_log_error($origine, $code_erreur, $message, $parametres, $last_request, $last_response, $trace);
            }

            if (!is_object($this->client)) {
                $origine       = 'Client_WS_Chronopost::connect';
                $code_erreur   = self::ERR_FATAL;
                $parametres    = 'wsdl:' . $this->wsdl;
                $message       = "Problème d'instanciation du SoapClient";
                $trace         = null;
                $last_request  = null;
                $last_response = null;
                return self::chronopost_log_error($origine, $code_erreur, $message, $parametres, $last_request, $last_response, $trace);
            }

            $this->client->setSource('Client_WS_Chronopost');
            return true;
        }
    }


    /**
     * Loggue les erreurs
     * @param string $origine Origine de l'erreur. Format = Classe::Méthode
     * @param integer $code_erreur Code d'erreur
     * @param string $message_erreur Message d'erreur
     * @param string $parametres Paramètre de la méthode en erreur
     * @param string $last_request Dernière requête SOAP envoyée
     * @param string $last_response Dernière réponse SOAP reçue
     * @return boolean Poursuite du script? (true/false)
     */
    public function chronopost_log_error($origine, $code_erreur, $message_erreur, $parametres, $last_request = null, $last_response = null, $trace = null)
    {

        //Affiche l'erreur
        if ($this->debug)
            echo "\n***** Erreur : $message_erreur\t";

        //Récupère le type d'erreur
        switch ($code_erreur) {

            case self::ERR_FATAL:
                $type = 'FATALE';
                break;
            case self::ERR_SOAP:
                $type = 'SOAP';
                break;
            case self::ERR_SQL:
                $type = 'SQL';
                break;
            case self::ERR_NO_DATA:
                $type = 'NO DATA';
                break;
            case self::ERR_REJET:
                $type = 'RejetDevis';
                break;
            default:
                $type = 'INCONNUE';
        }


        //Gestion LOG **********************************************************
        if ($this->log) {

            //Log des infos communes
            Log::ecrireLog("*** Erreur **************************", $this->fichier_log);
            Log::ecrireLog("* Origine: $origine", $this->fichier_log);
            Log::ecrireLog("* Type d'erreur: $type", $this->fichier_log);
            Log::ecrireLog("* Paramètres: $parametres", $this->fichier_log);
            Log::ecrireLog("* Message d'erreur: $message_erreur", $this->fichier_log);

            //Log de la trace de la pile si dispo
            if ($trace != null) {
                Log::ecrireLog("* Trace : $trace", $this->fichier_log);
            }

            //Log des trames SOAP uniquement si erreur WS
            if ($last_request != null) {
                Log::ecrireLog("* Dernière Requête SOAP : $last_request", $this->fichier_log);
            }

            if ($last_response != null) {
                Log::ecrireLog("* Dernière Réponse SOAP : $last_response", $this->fichier_log);
            }

            Log::ecrireLog('', $this->fichier_log);
            Log::ecrireLog('********************************************************************************************************************************************', $this->fichier_log);
            Log::ecrireLog('', $this->fichier_log);
        }


        //Gestion SESSION ******************************************************
        if (isset($_SESSION)) {

            $_SESSION['tabErreur'][] = "<span style='color:black; font-size:14px;'>";
            $_SESSION['tabErreur'][] = "Erreur WS Chronopost:";

            //Infos communes
            $_SESSION['tabErreur'][] = "* <span style='color:#FF0000; font-size:14px;'>Origine:</span> $origine";
            $_SESSION['tabErreur'][] = "* <span style='color:#FF0000; font-size:14px;'>Type d'erreur:</span> $type";
            $_SESSION['tabErreur'][] = "* <span style='color:#FF0000; font-size:14px;'>Paramètres:</span> $parametres";
            $_SESSION['tabErreur'][] = "* <span style='color:#FF0000; font-size:14px;'>Message d'erreur:</span> $message_erreur";

            //Trace de la pile si dispo
            if ($trace != null) {
                $_SESSION['tabErreur'][] = "* <span style='color:#FF0000; font-size:14px;'>Trace:</span> $trace";
            }

            //Trames SOAP uniquement si erreur WS
            if ($last_request != null) {
                $_SESSION['tabErreur'][] = "* <span style='color:#FF0000; font-size:14px;'>Dernière Requête SOAP:</span><pre>" . htmlspecialchars($last_request) . "</pre>";
            }

            if ($last_response != null) {
                $_SESSION['tabErreur'][] = "* <span style='color:#FF0000; font-size:14px;'>Dernière Réponse SOAP:</span><pre>" . htmlspecialchars($last_response) . "</pre>";
            }


            $_SESSION['tabErreur'][] = "</span>";
        }

        return false;
    }


    public function shippingMultiParcelWithReservation(){

        $civility = 'M';
        $surname = 'André';
        $firstname = 'DESFEYSSE';
        $adress = '69 rue de la république';
        $adress2 = 'a gauche au fond de l impasse';
        $city = 'Alix';
        $countrycode = 'FR';
        $countryname = 'FRANCE';
        $completename = 'André DESFEYSSE';
        $email = 'Test@gmail.com';
        $mobilephonenumber = '0696911001';
        $phonenumber = '0449691100';
        $zipcode = '69210';
        $commandeNo = '000000000000001';
        $articleNo ='24';
        $productcode = '1O';
        //Generate a date at ISO 8601 format 2004-02-12T15:19:21+00:00
        $shippingdate = date('c');
        $weight = '2';
        $numberofparcel = '2';
        $height = '10';
        $length = '20';
        $width = '20';

        $params = array(

            //STRUCTURE HEADER VALUE
            'headerValue' => array(
                'accountNumber' => '19869502',
                'idEmit' => 'CHFR',
                'identWebPro' => '',
                'subAccount' => '',
            ),
            //STRUCTURE SHIPPERVALUE
            'shipperValue' => array(
                'shipperAdress1' => $adress,
                'shipperAdress2' => $adress2,
                'shipperCity' => $city,
                'shipperCivility' => $civility,
                'shipperContactName' => $completename,
                'shipperCountry' => $countrycode,
                'shipperCountryName' => $countryname,
                'shipperEmail' => $email,
                'shipperMobilePhone' => $mobilephonenumber,
                'shipperName' => $surname,
                'shipperName2' => $firstname,
                'shipperPhone' => $phonenumber,
                'shipperPreAlert' => 0,
                'shipperZipCode' => $zipcode,
            ),
            //STRUCTURE CUSTOMERVALUE
            'customerValue' => array(
                'customerAdress1' =>'40 RUE JEAN JAURES',
                'customerAdress2' => '',
                'customerCity' =>'BAGNOLET',
                'customerCivility' =>'M',
                'customerContactName' =>'Jean MARTIN',
                'customerCountry' =>'FR',
                'customerCountryName' =>'FRANCE',
                'customerEmail' =>'client@mail.fr',
                'customerMobilePhone' =>'0611223344',
                'customerName' =>'ETABLISSEMENTS ET FILS',
                'customerName2' => '',
                'customerPhone' =>'0133333333',
                'customerPreAlert' => 0,
                'customerZipCode' =>'93170',
                'printAsSender' =>'N',
            ),
            //STRUCTURE RECIPIENTVALUE
            'recipientValue' => array(
                'recipientAdress1' => '3 Route des Pierres noires',
                'recipientAdress2' => '',
                'recipientCity' => 'Eugrèzie la Nevarre',
                'recipientContactName' => 'BRIBES DINFO',
                'recipientCountry' => 'FR',
                'recipientCountryName' => 'FRANCE',
                'recipientEmail' => 'test@gmail.com',
                'recipientMobilePhone' => '06666666',
                'recipientName' => 'BRIBES DINFO',
                'recipientName2' => '',
                'recipientPhone' => '044444444',
                'recipientPreAlert' => 0,
                'recipientZipCode' => '06920',
                //'recipientCivility' => 'M',
            ),
            //STRUCTURE REFVALUE
            'refValue' => array (
                'customerSkybillNumber' => '123456789',
                'PCardTransactionNumber' =>'',
                'recipientRef' => $articleNo,
                'shipperRef' => $commandeNo,
            ),
            //STRUCTURE SKYBILLVALUE
            'skybillValue' => array(
                'bulkNumber' => 2,
                'codCurrency' => 'EUR',
                'codValue' => 0,
                'customsCurrency' =>'EUR',
                'customsValue' => 0,
                'evtCode' => 'DC',
                'insuredCurrency' => 'EUR',
                'insuredValue' => 0,
                'masterSkybillNumber' => '?',
                'objectType' => 'MAR',
                'portCurrency' =>'EUR',
                'portValue' => 0,
                'productCode' => $productcode,
                'service' => '0',
                'shipDate' => $shippingdate,
                'shipHour' => '10',
                'skybillRank' => 1,
                'weight' => $weight,
                'weightUnit' => 'KGM',
                'height' => $height,
                'length' => $length,
                'width' => $width,
            ),
            //STRUCTURE SKYBILLPARAMSVALUE
            'skybillParamsValue' => array(
                'mode' => 'PDF',
                'withReservation' => 1,
            ),
            //OTHERS
            'password' => '255562',
            'modeRetour' => '2',
            'numberOfParcel' => $numberofparcel,
            'version' => '2.0',
            'multiparcel' => 'Y',
        );

        var_dump($this->client->__getFunctions());
        var_dump($this->client->__getTypes());


        try {
            $results = $this->client->shippingMultiParcelWithReservation($params);
            ?><pre><?php
            print_r($results);
            ?></pre><?php

           // echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
           echo "Response MULTI :<br>", htmlentities($this->client->__getLastResponse()), "<br>";
        }
        catch (SoapFault $soapFault) {
            var_dump($soapFault);
            //echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
            //echo "Response :<br>", htmlentities($this->client->__getLastResponse()), "<br>";
        }
        if( isset($results)){
            if(!empty($results->return->reservationNumber)){
                $reservationNumber = $results->return->reservationNumber;
                echo $reservationNumber;
                $test = $results->return->resultParcelValue;

                ?><p style="margin-top:100px;">RESULTAT DE resultParcelValue</p><pre><?php

                print_r($test);
                ?></pre><?php

                try {

                    ?><p style="margin-top:100px;">RESULTAT DE getReservedSkybill</p><pre><?php

                    $params = array('reservationNumber' => $reservationNumber);
                    $results = $this->client->getReservedSkybill($params);
                    //print_r($results);
                    //var_dump($results);
                    //var_dump($results->return->skybill) ;
                    echo 'var_dump de l\'etiquette : ';
                    var_dump($results->return->skybill);
                    ?></pre><?php

                    echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
                    echo "Response :<br>", htmlentities($this->client->__getLastResponse()), "<br>";




                $file_retour = SITE_DIR . "/documents/pdf/tmp/testestestNICO".time().".pdf";
                $etiquette = $results->return->skybill;
                //var_dump($etiquette);
                $etiq_decode = base64_decode($etiquette);
                file_put_contents($file_retour, $etiq_decode);



                }
                catch (SoapFault $soapFault) {
                    var_dump($soapFault);
                    echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
                    echo "Response :<br>", htmlentities($this->client->__getLastResponse()), "<br>";
                }
            }
            else{
                foreach($results->return as $key => $value) {
                    echo 'test : '.$key.' '.$value;
                }
            }


        }
    }
}