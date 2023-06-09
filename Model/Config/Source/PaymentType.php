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

    namespace Hippiemonkeys\Dhl\Model\Config\Source;

    use Magento\Framework\Data\OptionSourceInterface,
        Hippiemonkeys\Dhl\Model\Carrier;

    class PaymentType
    implements OptionSourceInterface
    {
        /**
         * @inheritdoc
         */
        public function toOptionArray()
        {
            return [
                ['value' => Carrier::PAYMENT_TYPE_SHIPPER, 'label' => __('Shipper')],
                ['value' => Carrier::PAYMENT_TYPE_RECEIVER, 'label' =>__('Receiver')],
                ['value' => Carrier::PAYMENT_TYPE_THIRDPARTY, 'label' =>__('Third Party')]
            ];
        }
    }
?>