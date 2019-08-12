<?php

/**
 * @changelog
 */

/**
 * @author      Nicolas Ulmann
 * @created		10/08/2016
 *
 * @description	Classe permettant de générer une étiquette Chronopost par webservice
 *
 * @version
 * 				23/01/2019:     #INIT: verison initiale (nulmann)
 *
 * @changelog
 */
class Client_WS_Chronopost
{

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
                $this->client = new SoapClient(WS_CHRONOPOST_SHIPPING_URL,array('trace' => 1,'soap_version'  => SOAP_1_1,
                    'encoding' => 'UTF-8'));


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


    public function shippingMultiParcelWithReservation($dos_id, $destinataire, $expediteur, $config, $swap=true)
    {


        //Generate a date at ISO 8601 format 2004-02-12T15:19:21+00:00
        $shippingDate   = date('c');
        $shippingHour   = date("G");
        $numberofparcel = ($swap)?'2':'1'; // nombre de colis - SWAP = 2 sinon 1
        $codeProduit    = explode(',',$config->CODE_PRODUIT);




        // LISTE DES PARAMETRES
        $params = array(

            //STRUCTURE HEADER VALUE
            'headerValue' => array(
                'accountNumber' => $config->NUM_COMPTE,
                'idEmit' => 'CHRFR',
                'identWebPro' => '',
                'subAccount' => '003',
            ),
            //STRUCTURE SHIPPERVALUE
            'shipperValue' => array(
                'shipperAdress1'    => mb_strtoupper($expediteur->ADRESSE1_CRTI),
                'shipperAdress2'    => mb_strtoupper($expediteur->ADRESSE2_CRTI),
                'shipperCity'       => mb_strtoupper($expediteur->VILLE_CRTI),
                'shipperCivility'   => 'M',
                //'shipperContactName' => $completename,
                'shipperCountry'    => mb_strtoupper($expediteur->PAYS_CRTI),
                //'shipperCountryName' => mb_strtoupper($expediteur->PAYS_CRTI),
                'shipperEmail'      => mb_strtoupper($expediteur->EMAIL_CRTI),
                //'shipperMobilePhone' => $mobilephonenumber,
                'shipperName'       => mb_strtoupper($expediteur->NOM_CRTI),
                //'shipperName2'    => $firstname,
                'shipperPhone'      => mb_strtoupper($expediteur->TEL_CRTI),
                'shipperPreAlert'   => 0,
                'shipperZipCode'    => mb_strtoupper($expediteur->CODE_POSTAL_CRTI),
            ),
            //STRUCTURE CUSTOMERVALUE
            'customerValue' => array(
                'customerAdress1'       => mb_strtoupper($expediteur->ADRESSE1_CRTI),
                'customerAdress2'       => mb_strtoupper($expediteur->ADRESSE2_CRTI),
                'customerCity'          => mb_strtoupper($expediteur->VILLE_CRTI),
                'customerCivility'      => 'E',
                'customerContactName'   => mb_strtoupper($expediteur->PRENOM_REF_CRTI.' '.$expediteur->NOM_REF_CRTI),
                'customerCountry'       => mb_strtoupper($expediteur->PAYS_CRTI),
                //'customerCountryName' =>'FRANCE',
                'customerEmail'         => mb_strtoupper($expediteur->EMAIL_CRTI),
                //'customerMobilePhone'   => '0611223344',
                'customerName'          => mb_strtoupper($expediteur->NOM_CRTI),
                'customerName2'         => '',
                'customerPhone'         => mb_strtoupper($expediteur->TEL_CRTI),
                'customerPreAlert'      => 0,
                'customerZipCode'       => mb_strtoupper($expediteur->CODE_POSTAL_CRTI),
                'printAsSender'         => 'N',
            ),
            //Adresse Destinataire
            'recipientValue' => array(
                'recipientAdress1'      => mb_strtoupper($destinataire->ADRESSE1_CLIENT),
                'recipientAdress2'      => mb_strtoupper($destinataire->ADRESSE2_CLIENT),
                'recipientCity'         => mb_strtoupper($destinataire->VILLE_CLIENT),
                'recipientContactName'  => mb_strtoupper($destinataire->PRENOM_CLIENT.' '.$destinataire->NOM_CLIENT),
                'recipientCountry'      => mb_strtoupper($destinataire->PAYS_CLIENT),
                //'recipientCountryName'  => 'FRANCE',
                'recipientEmail'        => mb_strtoupper($destinataire->EMAIL_CLIENT),
                'recipientMobilePhone'  => mb_strtoupper($destinataire->TEL_CLIENT),
                'recipientName'         => mb_strtoupper($destinataire->SOC_CLIENT),
                'recipientName2'        => '',
                'recipientPhone'        => mb_strtoupper($destinataire->TEL_CLIENT),
                'recipientPreAlert'     => 0,
                'recipientZipCode'      => mb_strtoupper($destinataire->CODE_POSTAL_CLIENT),
                //'recipientCivility' => 'M',
            ),
            //STRUCTURE REFVALUE
            'refValue' => array(
                'customerSkybillNumber'     => '',
                //'PCardTransactionNumber'    => '',
                'recipientRef'              => $dos_id,
                'shipperRef'                => $dos_id,
            ),
            //STRUCTURE SKYBILLVALUE
           // $skybillValueArray = array()
            'skybillValue'      => array(
                    array(
                        'bulkNumber'    =>'1',
                        'skybillRank'   =>'1',
                        'evtCode'       => 'DC',
                        'productCode'   => $codeProduit[0], //SI contexte = swap $codeProduit[0] = 1O; $codeProduit[1] = 01; , sinon (DME_SIMPLE) $codeProduit[0] = 01;
                        'shipDate'      => $shippingDate,
                        'shipHour'      => $shippingHour,
                        'objectType'    => 'MAR', // marchandise
                        'service'       => '0', //jours de livraison 0 -> normal
                        'weight'        => $config->POIDS,
                        'weightUnit'    => 'KGM',
                        'height'        => '',
                        'length'        => '',
                        'width'         => ''
                        //'insuredValue' => 0,
                        //'insuredCurrency' => 'EUR',
                        //'customsValue' => 0, // util pour l'international
                        //'customsCurrency' => 'EUR',
                        //'codValue' => 0,
                        //'codCurrency' => 'EUR',
                        //'bulkNumber' => 2,
                        //'skybillRank' => 1, sûrement utile pour la refonte (multi colis)
                        //'masterSkybillNumber' => '?', sûrement utile pour la refonte (multi colis)
                    ),
            ),

            //STRUCTURE SKYBILLPARAMSVALUE
            'skybillParamsValue' => array(
                'mode'              => $config->FORMAT_ETIQUETTE,
                'withReservation'   => 1,
            ),
            //OTHERS
            'password'          => $config->PASSWORD_COMPTE,
            'modeRetour'        => '2',
            'numberOfParcel'    => $numberofparcel,
            'version'           => '2.0',
            'multiparcel'       => 'N', //utile pour la refonte (multi colis)
        );

            // Afficher les specs du WS Chrono
            //var_dump($this->client->__getFunctions());
            //var_dump($this->client->__getTypes());

        // Si swap, on duplique la skybill value avec le code produit qui va bien
        if($swap){
            $params['skybillValue'][] = array(
                        'bulkNumber'    =>'1',
                        'skybillRank'   =>'1',
                        'evtCode'       => 'DC',
                        'productCode'   => $codeProduit[1],
                        'shipDate'      => $shippingDate,
                        'shipHour'      => $shippingHour,
                        'objectType'    => 'MAR', // marchandise
                        'service'       => '0', //jours de livraison 0 -> normal
                        'weight'        => $config->POIDS,
                        'weightUnit'    => 'KGM',
                        'height'        => '',
                        'length'        => '',
                        'width'         => ''
                    );
        }



        try {

            $results = $this->client->shippingMultiParcelWithReservation($params);



            //echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
            //echo "Response MULTI :<br>", htmlentities($this->client->__getLastResponse()), "<br>";
        } catch (SoapFault $soapFault) {
            var_dump($soapFault);
            //echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
            //echo "Response :<br>", htmlentities($this->client->__getLastResponse()), "<br>";
        }
        if (isset($results)) {
            if (!empty($results->return->reservationNumber)) {

                $reservationNumber      = $results->return->reservationNumber;

                if ($swap){
                    $num_tracking_aller     = $results->return->resultParcelValue[0]->skybillNumber;
                    $num_tracking_retour    = $results->return->resultParcelValue[1]->skybillNumber;

                }
                else{ //tracking aller seulement (ex : génération manuelle de trackings)
                    $num_tracking_aller     = $results->return->resultParcelValue->skybillNumber;
                    $num_tracking_retour    = '';
                }


                try {

                    //$params  = array('reservationNumber' => $reservationNumber);
                    //$results = $this->client->getReservedSkybill($params);
                    //$etiquette = $results->return->skybill;

                    $formatRetour   = explode('|',$config->FORMAT_ETIQUETTE);
                    $files = array();
                    //On récupère une étiquette dans chaque format configuré (PDF|ZPL300 etc)
                    foreach ($formatRetour as $item) {
                        $params         = array('reservationNumber' => $reservationNumber, 'mode' => $item);
                        $results        = $this->client->getReservedSkybillWithTypeAndMode($params);
                        $files[$item]   = $results->return->skybill;
                    }

                /*
                    //Recup étiquette PDF
                    $params  = array('reservationNumber' => $reservationNumber, 'mode' => 'PDF');
                    $results = $this->client->getReservedSkybillWithTypeAndMode($params);
                    $pdf_file = $results->return->skybill;

                    echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
                    echo "Response :<br>", htmlentities($this->client->__getLastResponse()), "<br>";

                    //Recup étiquette ZPL
                    $params  = array('reservationNumber' => $reservationNumber, 'mode' => 'ZPL300');
                    $results = $this->client->getReservedSkybillWithTypeAndMode($params);
                    $zpl_file = $results->return->skybill;

                    echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
                    echo "Response :<br>", htmlentities($this->client->__getLastResponse()), "<br>";
                    */

                    // Si on veut ajouter un format, il faudra ajouter la colonne dans la table
                    $parametres = array(
                        "REF_INTERNE"               => $dos_id,
                        "NUM_TRACKING_ALLER"        => $num_tracking_aller,
                        "NUM_TRACKING_RETOUR"       => $num_tracking_retour,
                        "ZPL_FILE"                  => $files['ZPL300'],
                        "PDF_FILE"                  => $files['PDF'],
                        "TRACKING_RESERVATION_NUM"  => $reservationNumber
                    );
                    //sauvegarde les données en base
                    $result = array_to_sql('T_EDI_CHRONOPOST_WS', $parametres);

                    if($result !== true){

                        $parametres = json_encode($parametres);

                        return self::chronopost_log_error(
                            'Client_WS_CHRONOPOST->expedition',	//$origine
                            self::ERR_SQL,			//$code_erreur
                            $result,							//$message_erreur
                            $parametres,						//$parametres
                            $this->client->__getLastRequest(),	//$last_request
                            $this->client->__getLastResponse()	//$last_response
                        );
                    }

                    return array(
                        "num_tracking_aller"    => $num_tracking_aller,
                        "num_tracking_retour"   => $num_tracking_retour,
                        "files"                 => $files,
                        "res_number"            => $reservationNumber
                    );


                } catch (SoapFault $soapFault) {
                    var_dump($soapFault);
                    echo "Request :<br>", htmlentities($this->client->__getLastRequest()), "<br>";
                    echo "Response :<br>", htmlentities($this->client->__getLastResponse()), "<br>";
                }
            }
            else {
                foreach ($results->return as $key => $value) {
                    echo 'Erreur du webservice Chronopost : ' . $key . ' ' . $value;
                }
            }


        }
    }
}