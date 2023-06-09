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
        Magento\Dhl\Model\Carrier as MagentoCarrier,
        Hippiemonkeys\Dhl\Model\Carrier as HippiemonkeysCarrier;

    class Model
    implements OptionSourceInterface
    {
        /**
         * @inheritdoc
         */
        public function toOptionArray()
        {
            return [
                ['value' => MagentoCarrier::class, 'label' => __('Native')],
                ['value' => HippiemonkeysCarrier::class, 'label' => __('Hippiemonkeys')]
            ];
        }
    }
?>