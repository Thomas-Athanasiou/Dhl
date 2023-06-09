<?php
    /**
     * @Thomas-Athanasiou
     *
     * @author Thomas Athanasiou {thomas@hippiemonkeys.com}
     * @link https://hippiemonkeys.com
     * @link https://github.com/Thomas-Athanasiou
     * @copyright Copyright (c) 2023 Hippiemonkeys Web Intelligence EE All Rights Reserved.
     * @license http://www.gnu.org/licenses/ GNU General Public License, version 3
     * @package Hippiemonkeys_Dhl
     */

    declare(strict_types=1);

    namespace Hippiemonkeys\Dhl\Model;

    use Psr\Log\LoggerInterface,
        Magento\Framework\Exception\LocalizedException,
        Magento\Dhl\Model\Carrier as ParentCarrier,
        Magento\Framework\App\ObjectManager,
        Magento\Framework\App\ProductMetadataInterface,
        Magento\Framework\Async\CallbackDeferred,
        Magento\Framework\HTTP\AsyncClient\HttpException,
        Magento\Framework\HTTP\AsyncClient\HttpResponseDeferredInterface,
        Magento\Framework\HTTP\AsyncClient\Request,
        Magento\Framework\HTTP\AsyncClientInterface,
        Magento\Framework\Xml\Security,
        Magento\Sales\Model\Order\Shipment,
        Magento\Shipping\Model\Rate\Result,
        Magento\Shipping\Model\Rate\Result\ProxyDeferredFactory,
        Magento\Framework\App\Config\ScopeConfigInterface,
        Magento\Quote\Model\Quote\Address\RateRequest,
        Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory as RateErrorFactory,
        Magento\Shipping\Model\Simplexml\ElementFactory,
        Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory,
        Magento\Quote\Model\Quote\Address\RateResult\MethodFactory as RateMethodFactory,
        Magento\Shipping\Model\Tracking\ResultFactory as TrackFactory,
        Magento\Shipping\Model\Tracking\Result\ErrorFactory as TrackErrorFactory,
        Magento\Shipping\Model\Tracking\Result\StatusFactory as TrackStatusFactory,
        Magento\Directory\Model\RegionFactory,
        Magento\Directory\Model\CountryFactory,
        Magento\Directory\Model\CurrencyFactory,
        Magento\Directory\Helper\Data as DirectoryData,
        Magento\CatalogInventory\Api\StockRegistryInterface,
        Magento\Shipping\Helper\Carrier as CarrierHelper,
        Magento\Framework\Stdlib\DateTime\DateTime,
        Magento\Framework\Module\Dir\Reader as ConfigReader,
        Magento\Store\Model\Website,
        Magento\Store\Model\StoreManagerInterface,
        Magento\Framework\Stdlib\StringUtils,
        Magento\Framework\Math\Division as MathDivision,
        Magento\Framework\Filesystem\Directory\ReadFactory,
        Magento\Framework\Stdlib\DateTime as MagentoDateTime,
        Magento\Framework\HTTP\LaminasClientFactory,
        Magento\Dhl\Model\Validator\XmlValidator as DhlXmlValidator;

    class Carrier
    extends ParentCarrier
    {
        public const
            /**
             * Shipper Payment Type
             */
            PAYMENT_TYPE_SHIPPER    = 'S',

            /**
             * Receiver Payment Type
             */
            PAYMENT_TYPE_RECEIVER   = 'R',

            /**
             * Third Party Payment Type
             */
            PAYMENT_TYPE_THIRDPARTY = 'T';

        private const
            /**
             * DHL service prefixes overrides
             */
            SERVICE_PREFIX_QUOTE = 'QUOT',
            SERVICE_PREFIX_SHIPVAL = 'SHIP',
            SERVICE_PREFIX_TRACKING = 'TRCK';

        /**
         * Constructor
         *
         * @access public
         *
         * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
         * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
         * @param \Psr\Log\LoggerInterface $logger
         * @param \Magento\Framework\Xml\Security $xmlSecurity
         * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
         * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
         * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
         * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
         * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
         * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
         * @param \Magento\Directory\Model\RegionFactory $regionFactory
         * @param \Magento\Directory\Model\CountryFactory $countryFactory
         * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
         * @param \Magento\Directory\Helper\Data $directoryData
         * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
         * @param \Magento\Shipping\Helper\Carrier $carrierHelper
         * @param \Magento\Framework\Stdlib\DateTime\DateTime $coreDate
         * @param \Magento\Framework\Module\Dir\Reader $configReader
         * @param \Magento\Store\Model\StoreManagerInterface $storeManager
         * @param \Magento\Framework\Stdlib\StringUtils $string
         * @param \Magento\Framework\Math\Division $mathDivision
         * @param \Magento\Framework\Filesystem\Directory\ReadFactory $readFactory
         * @param \Magento\Framework\Stdlib\DateTime $dateTime
         * @param \Magento\Framework\HTTP\LaminasClientFactory $httpClientFactory
         * @param array $data
         * @param \Magento\Dhl\Model\Validator\XmlValidator|null $xmlValidator
         * @param \Magento\Framework\App\ProductMetadataInterface|null $productMetadata
         * @param \Magento\Framework\HTTP\AsyncClientInterface|null $httpClient
         * @param \Magento\Shipping\Model\Rate\Result\ProxyDeferredFactory|null $proxyDeferredFactory
         * @SuppressWarnings(PHPMD.ExcessiveParameterList)
         */
        public function __construct(
            ScopeConfigInterface $scopeConfig,
            RateErrorFactory $rateErrorFactory,
            LoggerInterface $logger,
            Security $xmlSecurity,
            ElementFactory $xmlElFactory,
            RateResultFactory $rateFactory,
            RateMethodFactory $rateMethodFactory,
            TrackFactory $trackFactory,
            TrackErrorFactory $trackErrorFactory,
            TrackStatusFactory $trackStatusFactory,
            RegionFactory $regionFactory,
            CountryFactory $countryFactory,
            CurrencyFactory $currencyFactory,
            DirectoryData $directoryData,
            StockRegistryInterface $stockRegistry,
            CarrierHelper $carrierHelper,
            DateTime $coreDate,
            ConfigReader $configReader,
            StoreManagerInterface $storeManager,
            StringUtils $string,
            MathDivision $mathDivision,
            ReadFactory $readFactory,
            MagentoDateTime $dateTime,
            LaminasClientFactory $httpClientFactory,
            array $data = [],
            DhlXmlValidator $xmlValidator = null,
            ProductMetadataInterface $productMetadata = null,
            ?AsyncClientInterface $httpClient = null,
            ?ProxyDeferredFactory $proxyDeferredFactory = null
        )
        {
            parent::__construct(
                $scopeConfig,
                $rateErrorFactory,
                $logger,
                $xmlSecurity,
                $xmlElFactory,
                $rateFactory,
                $rateMethodFactory,
                $trackFactory,
                $trackErrorFactory,
                $trackStatusFactory,
                $regionFactory,
                $countryFactory,
                $currencyFactory,
                $directoryData,
                $stockRegistry,
                $carrierHelper,
                $coreDate,
                $configReader,
                $storeManager,
                $string,
                $mathDivision,
                $readFactory,
                $dateTime,
                $httpClientFactory,
                $data,
                $xmlValidator,
                $productMetadata ,
                $httpClient ,
                $proxyDeferredFactory
            );
            $this->_httpClient = $httpClient ?? ObjectManager::getInstance()->get(AsyncClientInterface::class);
            $this->_proxyDeferredFactory = $proxyDeferredFactory ?? ObjectManager::getInstance()->get(ProxyDeferredFactory::class);
        }


        /**
         * {@inheritdoc}
         */
        protected function _getQuotes()
        {
            $responseBodies = [];
            /** @var HttpResponseDeferredInterface[][] $deferredResponses */
            $deferredResponses = [];
            $requestXml = $this->_buildQuotesRequestXml();
            for ($offset = 0; $offset <= self::UNAVAILABLE_DATE_LOOK_FORWARD; $offset++)
            {
                $date = date(self::REQUEST_DATE_FORMAT, strtotime($this->_getShipDate() . " +{$offset} days"));
                $this->_setQuotesRequestXmlDate($requestXml, $date);
                $request = $requestXml->asXML();
                $responseBody = $this->_getCachedQuotes($request);

                if ($responseBody === null)
                {
                    $deferredResponses[] = [
                        /**
                         * THOMAS - EDIT - ΑΛΛΑΓΗ ΠΡΟΒΛΗΜΑ ΣΤΟ SANDBOX MODE: ΑΡΧΗ
                         */
                        'deferred' => $this->getHttpClient()->request(
                            new Request(
                                (string)$this->getGatewayUrl(),
                                Request::METHOD_POST,
                                ['Content-Type' => 'application/xml'],
                                mb_convert_encoding($request, 'UTF-8')
                            )
                        ),
                        /**
                         * THOMAS - EDIT - ΑΛΛΑΓΗ ΠΡΟΒΛΗΜΑ ΣΤΟ SANDBOX MODE: ΤΕΛΟΣ
                         */
                        'date' => $date,
                        'request' => $request
                    ];
                }
                else
                {
                    $responseBodies[] = [
                        'body' => $responseBody,
                        'date' => $date,
                        'request' => $request,
                        'from_cache' => true
                    ];
                }
            }

            return $this->getProxyDeferredFactory()->create(
                [
                    'deferred' => new CallbackDeferred(
                        function () use ($deferredResponses, $responseBodies)
                        {
                            //Loading rates not found in cache
                            foreach ($deferredResponses as $deferredResponseData)
                            {
                                $responseResult = null;
                                try
                                {
                                    $responseResult = $deferredResponseData['deferred']->get();
                                }
                                catch (HttpException $exception)
                                {
                                    $this->getLogger()->critical($exception);
                                }
                                $responseBody = $responseResult ? $responseResult->getBody() : '';
                                $responseBodies[] = [
                                    'body' => $responseBody,
                                    'date' => $deferredResponseData['date'],
                                    'request' => $deferredResponseData['request'],
                                    'from_cache' => false
                                ];
                            }

                            return $this->processQuotesResponses($responseBodies);
                        }
                    )
                ]
            );
        }

        /**
         * @inheritdoc
         */
        protected function _shipmentDetails($xml, $rawRequest, $originRegion = '')
        {
            $nodeShipmentDetails = $xml->addChild('ShipmentDetails', '', '');

            $nodePieces = $nodeShipmentDetails->addChild('Pieces', '', '');

            /*
             * Package type
             * EE (DHL Express Envelope), OD (Other DHL Packaging), CP (Custom Packaging)
             * DC (Document), DM (Domestic), ED (Express Document), FR (Freight)
             * BD (Jumbo Document), BP (Jumbo Parcel), JD (Jumbo Junior Document)
             * JP (Jumbo Junior Parcel), PA (Parcel), DF (DHL Flyer)
             */
            $i = 0;

            /**
             * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΤΙΤΛΩΝ ΕΙΔΩΝ ΣΤΟ ΠΕΔΙΟ "CONTENTS": ΑΡΧΗ
             */
            $shipmentContents = [];
            $stringUtils = $this->getStringUtils();
            /**
             * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΤΙΤΛΩΝ ΕΙΔΩΝ ΣΤΟ ΠΕΔΙΟ "CONTENTS": ΤΕΛΟΣ
             */
            foreach ($rawRequest->getPackages() as $package)
            {
                $nodePiece = $nodePieces->addChild('Piece', '', '');
                $packageType = 'EE';
                if ($package['params']['container'] == self::DHL_CONTENT_TYPE_NON_DOC)
                {
                    $packageType = 'CP';
                }
                $nodePiece->addChild('PieceID', ++$i);
                $nodePiece->addChild('PackageType', $packageType);
                $nodePiece->addChild('Weight', sprintf('%.3f', $package['params']['weight']));
                $params = $package['params'];

                if ($params['width'] && $params['length'] && $params['height'])
                {
                    $nodePiece->addChild('Width', (string) round((float) $params['width']));
                    $nodePiece->addChild('Height', (string) round((float) $params['height']));
                    $nodePiece->addChild('Depth', (string) round((float) $params['length']));
                }
                $content = [];
                foreach ($package['items'] as $item)
                {
                    $content[] = $item['name'];
                }

                /**
                 * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΤΙΤΛΩΝ ΕΙΔΩΝ ΣΤΟ ΠΕΔΙΟ "CONTENTS": ΑΡΧΗ
                 */
                $packageContent = $stringUtils->substr(implode(',', $content), 0, 34);
                $shipmentContents[] = $packageContent;
                $nodePiece->addChild('PieceContents', $packageContent);
                /**
                 * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΤΙΤΛΩΝ ΕΙΔΩΝ ΣΤΟ ΠΕΔΙΟ "CONTENTS": ΤΕΛΟΣ
                 */
            }

            $nodeShipmentDetails->addChild('WeightUnit', substr($this->_getWeightUnit(), 0, 1));
            $nodeShipmentDetails->addChild('GlobalProductCode', $rawRequest->getShippingMethod());
            $nodeShipmentDetails->addChild('LocalProductCode', $rawRequest->getShippingMethod());
            $nodeShipmentDetails->addChild('Date', $this->getCoreDate()->date('Y-m-d', strtotime('now + 1day')));

            /**
             * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΤΙΤΛΩΝ ΕΙΔΩΝ ΣΤΟ ΠΕΔΙΟ "CONTENTS": ΑΡΧΗ
             */
            /* $nodeShipmentDetails->addChild('Contents', 'DHL Parcel');*/
            $nodeShipmentDetails->addChild('Contents', $stringUtils->substr(implode(',', $shipmentContents), 0, 500));
            /**
             * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΤΙΤΛΩΝ ΕΙΔΩΝ ΣΤΟ ΠΕΔΙΟ "CONTENTS": ΤΕΛΟΣ
             */

            $nodeShipmentDetails->addChild('DimensionUnit', substr($this->_getDimensionUnit(), 0, 1));
            $contentType = isset($package['params']['container']) ? $package['params']['container'] : '';
            $packageType = $contentType === self::DHL_CONTENT_TYPE_NON_DOC ? 'CP' : 'EE';
            $nodeShipmentDetails->addChild('PackageType', $packageType);
            if ($this->isDutiable($rawRequest->getShipperAddressCountryCode(), $rawRequest->getRecipientAddressCountryCode()))
            {
                $nodeShipmentDetails->addChild('IsDutiable', 'Y');
            }

            /**
             * THOMAS - EDIT - TYPE ERROR: ΑΡΧΗ
             */
            $baseCurrencyCode = 'USD';
            $requestWebsite = $this->getStoreManager()->getWebsite($this->getRequest()->getWebsiteId());
            if($requestWebsite instanceof Website)
            {
                $baseCurrencyCode = $requestWebsite->getBaseCurrencyCode();
            }
            $nodeShipmentDetails->addChild('CurrencyCode', $baseCurrencyCode);
            /**
             * THOMAS - EDIT - TYPE ERROR: ΤΕΛΟΣ
             */
        }

        /**
         * {@inheritdoc}
         */
        protected function _doRequest()
        {
            /**
             * THOMAS - EDIT - STRING UTILS: ΑΡΧΗ
             */
            $stringUtils = $this->getStringUtils();
            /**
             * THOMAS - EDIT - STRING UTILS: ΤΕΛΟΣ
             */

            $rawRequest = $this->getRequest();

            $xmlStr = '<?xml version="1.0" encoding="UTF-8"?>' .
                '<req:ShipmentRequest' .
                ' xmlns:req="http://www.dhl.com"' .
                ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
                ' xsi:schemaLocation="http://www.dhl.com ship-val-global-req.xsd"' .
                ' schemaVersion="10.0" />';
            $xml = $this->getXmlElFactory()->create(['data' => $xmlStr]);

            $nodeRequest = $xml->addChild('Request', '', '');
            $nodeServiceHeader = $nodeRequest->addChild('ServiceHeader');
            $nodeServiceHeader->addChild('MessageTime', $this->buildMessageTimestamp());
            // MessageReference must be 28 to 32 chars.
            $nodeServiceHeader->addChild(
                'MessageReference',
                $this->buildMessageReference(self::SERVICE_PREFIX_SHIPVAL)
            );
            $nodeServiceHeader->addChild('SiteID', (string)$this->getConfigData('id'));
            $nodeServiceHeader->addChild('Password', (string)$this->getConfigData('password'));

            $nodeMetaData = $nodeRequest->addChild('MetaData');
            $nodeMetaData->addChild('SoftwareName', $this->buildSoftwareName());
            $nodeMetaData->addChild('SoftwareVersion', $this->buildSoftwareVersion());

            $originRegion = $this->getCountryParams(
                $this->getScopeConfig()->getValue(
                    Shipment::XML_PATH_STORE_COUNTRY_ID,
                    ScopeInterface::SCOPE_STORE,
                    $this->getStore()
                )
            )->getRegion();

            if ($originRegion)
            {
                $xml->addChild('RegionCode', $originRegion, '');
            }
            $xml->addChild('RequestedPickupTime', 'N', '');
            $xml->addChild('LanguageCode', 'EN', '');

            /** Billing */
            $nodeBilling = $xml->addChild('Billing', '', '');
            $nodeBilling->addChild('ShipperAccountNumber', (string)substr($this->getConfigData('account'), 0, 9));

            /**
             * Method of Payment:
             * S (Shipper)
             * R (Receiver)
             * T (Third Party)
             */
            $nodeBilling->addChild('ShippingPaymentType', 'S');

            /**
             * Shipment bill to account – required if Shipping PaymentType is other than 'S'
             */
            $nodeBilling->addChild('BillingAccountNumber', (string)substr($this->getConfigData('account'), 0, 9));

            /**
             * THOMAS - EDIT - ΑΛΛΑΓΗ DHL BULLET POINT 4: ΑΡΧΗ
             */
            $isDutiable = $this->isDutiable($rawRequest->getShipperAddressCountryCode(), $rawRequest->getRecipientAddressCountryCode());
            if($isDutiable)
            {
                $dutyPaymentType = $this->getDutyPaymentType();
                $nodeBilling->addChild('DutyPaymentType', $dutyPaymentType);
                if($dutyPaymentType == self::PAYMENT_TYPE_SHIPPER)
                {
                    $nodeBilling->addChild('DutyAccountNumber', (string)$this->getConfigData('account'));
                }
                /* $nodeBilling->addChild('DutyAccountNumber', (string)substr($this->getConfigData('account'), 0, 9));*/
            }
            /**
             * THOMAS - EDIT - ΑΛΛΑΓΗ DHL BULLET POINT 4: ΤΕΛΟΣ
             */

            /** Receiver */
            $nodeConsignee = $xml->addChild('Consignee', '', '');

            $companyName = $rawRequest->getRecipientContactCompanyName() ? $rawRequest->getRecipientContactCompanyName() : $rawRequest->getRecipientContactPersonName();

            $nodeConsignee->addChild('CompanyName', is_string($companyName) ? substr($companyName, 0, 60) : '');

            $address = $rawRequest->getRecipientAddressStreet1() . ' ' . $rawRequest->getRecipientAddressStreet2();

            /**
             * THOMAS - EDIT - STRING UTILS: ΑΡΧΗ
             */
            $address = $stringUtils->split($address, 45, false, true);
            /**
             * THOMAS - EDIT - STRING UTILS: ΤΕΛΟΣ
             */

            if (is_array($address))
            {
                $addressLineNumber = 1;
                foreach ($address as $addressLine)
                {
                    if ($addressLineNumber > 3)
                    {
                        break;
                    }
                    $nodeConsignee->addChild('AddressLine'.$addressLineNumber, $addressLine);
                    $addressLineNumber++;
                }
            }
            else
            {
                $nodeConsignee->addChild('AddressLine1', $address);
            }

            $nodeConsignee->addChild('City', $rawRequest->getRecipientAddressCity());
            $recipientAddressStateOrProvinceCode = $rawRequest->getRecipientAddressStateOrProvinceCode();
            if ($recipientAddressStateOrProvinceCode)
            {
                $nodeConsignee->addChild('Division', $recipientAddressStateOrProvinceCode);
            }
            $nodeConsignee->addChild('PostalCode', $rawRequest->getRecipientAddressPostalCode());
            $nodeConsignee->addChild('CountryCode', $rawRequest->getRecipientAddressCountryCode());
            $nodeConsignee->addChild('CountryName', $this->getCountryParams($rawRequest->getRecipientAddressCountryCode())->getName());
            $nodeContact = $nodeConsignee->addChild('Contact');
            $recipientContactPersonName = is_string($rawRequest->getRecipientContactPersonName()) ? substr($rawRequest->getRecipientContactPersonName(), 0, 34) : '';
            $recipientContactPhoneNumber = is_string($rawRequest->getRecipientContactPhoneNumber()) ? substr($rawRequest->getRecipientContactPhoneNumber(), 0, 24) : '';
            $nodeContact->addChild('PersonName', $recipientContactPersonName);
            $nodeContact->addChild('PhoneNumber', $recipientContactPhoneNumber);

            /**
             * Commodity
             * The CommodityCode element contains commodity code for shipment contents. Its
             * value should lie in between 1 to 9999.This field is mandatory.
             */
            $nodeCommodity = $xml->addChild('Commodity', '', '');
            $nodeCommodity->addChild('CommodityCode', substr('01', 0, 18));

            /** Dutiable */
            if($isDutiable)
            {
                $nodeDutiable = $xml->addChild('Dutiable', '', '');
                $nodeDutiable->addChild(
                    'DeclaredValue',
                    sprintf("%.2F", $rawRequest->getOrderShipment()->getOrder()->getSubtotal())
                );

                /**
                 * THOMAS - EDIT - TYPE ERROR: ΑΡΧΗ
                 */
                $baseCurrencyCode = 'USD';
                $requestWebsite = $this->getStoreManager()->getWebsite($rawRequest->getWebsiteId());
                if($requestWebsite instanceof Website)
                {
                    $baseCurrencyCode = $requestWebsite->getBaseCurrencyCode();
                }
                $nodeDutiable->addChild('DeclaredCurrency', $baseCurrencyCode);
                /**
                 * THOMAS - EDIT - TYPE ERROR: ΤΕΛΟΣ
                 */

                $nodeDutiable->addChild('TermsOfTrade', 'DAP');

                /** Export Declaration */
                $this->addExportDeclaration($xml, $rawRequest);
            }
            /**
             * Reference
             * This element identifies the reference information. It is an optional field in the
             * shipment validation request. Only the first reference will be taken currently.
             */
            $nodeReference = $xml->addChild('Reference', '', '');
            $nodeReference->addChild('ReferenceID', 'shipment reference');
            $nodeReference->addChild('ReferenceType', 'St');

            /** Shipment Details */
            $this->_shipmentDetails($xml, $rawRequest);

            /** Shipper */
            $nodeShipper = $xml->addChild('Shipper', '', '');
            $nodeShipper->addChild('ShipperID', (string)substr($this->getConfigData('account'), 0, 9));
            $nodeShipper->addChild('CompanyName', $rawRequest->getShipperContactCompanyName());
            $nodeShipper->addChild('RegisteredAccount', (string)substr($this->getConfigData('account'), 0, 9));

            $address = $rawRequest->getShipperAddressStreet1() . ' ' . $rawRequest->getShipperAddressStreet2();

            /**
             * THOMAS - EDIT - STRING UTILS: ΑΡΧΗ
             */
            $address = $stringUtils->split($address, 45, false, true);
            /**
             * THOMAS - EDIT - STRING UTILS: ΤΕΛΟΣ
             */

            if (is_array($address))
            {
                $addressLineNumber = 1;
                foreach ($address as $addressLine)
                {
                    if ($addressLineNumber > 3)
                    {
                        break;
                    }
                    $nodeShipper->addChild('AddressLine'.$addressLineNumber, $addressLine);
                    $addressLineNumber++;
                }
            }
            else
            {
                $nodeShipper->addChild('AddressLine1', $address);
            }

            $nodeShipper->addChild('City', $rawRequest->getShipperAddressCity());
            $shipperAddressStateOrProvinceCode = $rawRequest->getShipperAddressStateOrProvinceCode();
            if ($shipperAddressStateOrProvinceCode)
            {
                $nodeShipper->addChild('Division', $shipperAddressStateOrProvinceCode);
            }
            $nodeShipper->addChild('PostalCode', $rawRequest->getShipperAddressPostalCode());
            $nodeShipper->addChild('CountryCode', $rawRequest->getShipperAddressCountryCode());
            $nodeShipper->addChild('CountryName', $this->getCountryParams($rawRequest->getShipperAddressCountryCode())->getName());
            $nodeContact = $nodeShipper->addChild('Contact', '', '');
            $shipperContactPersonName = is_string($rawRequest->getShipperContactPersonName()) ? substr($rawRequest->getShipperContactPersonName(), 0, 34) : '';
            $shipperContactPhoneNumber = is_string($rawRequest->getShipperContactPhoneNumber()) ? substr($rawRequest->getShipperContactPhoneNumber(), 0, 24) : '';
            $nodeContact->addChild('PersonName', $shipperContactPersonName);
            $nodeContact->addChild('PhoneNumber', $shipperContactPhoneNumber);

            $xml->addChild('LabelImageFormat', 'PDF', '');

            $request = $xml->asXML();
            if ($request && !(mb_detect_encoding($request) == 'UTF-8'))
            {
                $request = mb_convert_encoding($request, 'UTF-8');
            }

            $responseBody = $this->_getCachedQuotes($request);
            if ($responseBody === null)
            {
                $debugData = ['request' => $this->filterDebugData($request)];
                try
                {
                    $response = $this->getHttpClient()->request(
                        new Request(
                            $this->getGatewayURL(),
                            Request::METHOD_POST,
                            ['Content-Type' => 'application/xml'],
                            $request
                        )
                    );
                    $responseBody = mb_convert_encoding($response->get()->getBody(), 'ISO-8859-1', 'UTF-8');
                    $debugData['result'] = $this->filterDebugData($responseBody);
                    $this->_setCachedQuotes($request, $responseBody);
                }
                catch (Exception $e)
                {
                    $this->_errors[$e->getCode()] = $e->getMessage();
                    $responseBody = '';
                }
                $this->_debug($debugData);
            }

            $this->_isShippingLabelFlag = true;

            return $this->_parseResponse($responseBody);
        }

        /**
         * Process response received from DHL's API for quotes.
         *
         * @param array $responsesData
         * @return Error|Result
         */
        private function processQuotesResponses(array $responsesData)
        {
            usort(
                $responsesData,
                function (array $a, array $b): int
                {
                    return $a['date'] <=> $b['date'];
                }
            );

            /** @var string $lastResponse */
            $lastResponse = '';
            //Processing different dates
            foreach ($responsesData as $responseData)
            {
                $debugPoint = [];
                $debugPoint['request'] = $this->filterDebugData($responseData['request']);
                $debugPoint['response'] = $this->filterDebugData($responseData['body']);
                $debugPoint['from_cache'] = $responseData['from_cache'];
                $unavailable = false;
                try
                {
                    //Getting availability
                    $bodyXml = $this->getXmlElFactory()->create(['data' => $responseData['body']]);
                    $code = $bodyXml->xpath('//GetQuoteResponse/Note/Condition/ConditionCode');
                    if (isset($code[0]) && (int)$code[0] == self::CONDITION_CODE_SERVICE_DATE_UNAVAILABLE)
                    {
                        $debugPoint['info'] = sprintf(__("DHL service is not available at %s date")->render(), $responseData['date']);
                        $unavailable = true;
                    }
                }
                catch (Throwable $exception)
                {
                    //Failed to read response
                    $unavailable = true;
                    $this->_errors[$exception->getCode()] = $exception->getMessage();
                }
                if ($unavailable)
                {
                    //Cannot get rates.
                    $this->_debug($debugPoint);
                    break;
                }
                //Caching rates
                $this->_setCachedQuotes($responseData['request'], $responseData['body']);
                $this->_debug($debugPoint);
                //Will only process rates available for the latest date possible.
                $lastResponse = $responseData['body'];
            }

            return $this->_parseResponse($lastResponse);
        }

        /**
         * buildMessageTimestamp() override
         *
         * @inheritdoc
         */
        private function buildMessageTimestamp(string $datetime = null): string
        {
            return $this->getCoreDate()->date(\DATE_RFC3339, $datetime);
        }

        /**
         * buildMessageReference() override
         *
         * @inheritdoc
         */
        private function buildMessageReference(string $servicePrefix): string
        {
            $validPrefixes = [self::SERVICE_PREFIX_QUOTE, self::SERVICE_PREFIX_SHIPVAL, self::SERVICE_PREFIX_TRACKING];

            if (!in_array($servicePrefix, $validPrefixes))
            {
                throw new LocalizedException(
                    __("Invalid service prefix \"$servicePrefix\" provided while attempting to build MessageReference")
                );
            }

            return str_replace('.', '', uniqid("MAGE_{$servicePrefix}_", true));
        }

        /**
         * getGatewayURL() override
         *
         * @inheritdoc
         */
        private function getGatewayURL(): string
        {
            return (string) $this->getConfigData(((bool) $this->getConfigData('sandbox_mode')) ? 'sandbox_url' : 'gateway_url');
        }

        /**
         * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΣΥΝΑΡΤΗΣΕΩΝ: ΑΡΧΗ
         */

        /*
         * Gets Duty Payment Type
         *
         * @access protected
         *
         * @return string
         */
        protected function getDutyPaymentType(): string
        {
            return (string)$this->getConfigData('duty_payment_type');
        }

        /**
         * Gets Store Manager
         *
         * @access protected
         *
         * @return \Magento\Store\Model\StoreManagerInterface
         */
        protected function getStoreManager(): StoreManagerInterface
        {
            return $this->_storeManager;
        }

        /**
         * Gets Request
         *
         * @access protected
         *
         * @return \Magento\Quote\Model\Quote\Address\RateRequest
         */
        protected function getRequest(): RateRequest
        {
            return $this->_request;
        }

        /**
         * Gets Logger
         *
         * @access protected
         *
         * @return \Psr\Log\LoggerInterface
         */
        protected function getLogger(): LoggerInterface
        {
            return $this->_logger;
        }

        /**
         * Gets String Utils
         *
         * @access protected
         *
         * @return \Magento\Framework\Stdlib\StringUtils
         */
        protected function getStringUtils(): StringUtils
        {
            return $this->string;
        }

        /**
         * Http Client property
         *
         * @access private
         *
         * @var \Magento\Framework\HTTP\AsyncClientInterface
         */
        private $_httpClient;

        /**
         * Gets Http Client
         *
         * @access protected
         *
         * @return \Magento\Framework\HTTP\AsyncClientInterface
         */
        protected function getHttpClient(): AsyncClientInterface
        {
            return $this->_httpClient;
        }

        /**
         * Gets Xml El Factory
         *
         * @access protected
         *
         * @return \Magento\Shipping\Model\Simplexml\ElementFactory
         */
        protected function getXmlElFactory(): ElementFactory
        {
            return $this->_xmlElFactory;
        }

        /**
         * Proxy Defered Factory property
         *
         * @access private
         *
         * @var \Magento\Shipping\Model\Rate\Result\ProxyDeferredFactory $_proxyDeferredFactory
         */
        private $_proxyDeferredFactory;

        /**
         * Gets Proxy Defered Factory
         *
         * @access private
         *
         * @var \Magento\Shipping\Model\Rate\Result\ProxyDeferredFactory
         */
        protected function getProxyDeferredFactory(): ProxyDeferredFactory
        {
            return $this->_proxyDeferredFactory;
        }

        /**
         * Gets Scope Config
         *
         * @access private
         *
         * @var \Magento\Framework\App\Config\ScopeConfigInterface
         */
        protected function getScopeConfig(): ScopeConfigInterface
        {
            return $this->_scopeConfig;
        }

        /**
         * Gets Core Date
         *
         * @access protected
         *
         * @var \Magento\Framework\Stdlib\DateTime\DateTime
         */
        protected function getCoreDate(): DateTime
        {
            return $this->_coreDate;
        }
        /**
         * THOMAS - EDIT - ΠΡΟΣΘΗΚΗ ΣΥΝΑΡΤΗΣΕΩΝ: ΤΕΛΟΣ
         */
    }
?>