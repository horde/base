<?php
/**
 * Copyright 2009-2019 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Michael J Rubinsky <mrubinsk.horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

/**
 * Defines the AJAX actions used for interacting with geonames.org
 *
 * @author   Michael J Rubinsky <mrubinsk.horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
class Horde_Ajax_Application_GeocodeHandler extends Horde_Core_Ajax_Application_Handler
{
    // TODO. Hardcode geonames implementation for now.
    const API_URL = 'https://secure.geonames.org/';

    /**
     * Perform a reverse geocode.
     * Expects:
     *     $vars->lat
     *     $vars->lon
     *
     * @return [type] [description]
     */
    public function reverseGeocode()
    {
        global $conf;

        if (empty($conf['api']['geonames'])) {
            throw new Horde_Exception('Missing required key parameter');
        }

        if (!$vars->lat || !$vars->lon) {
            throw new Horde_Exception('Missing coordinates.');
        }

        $url = new Horde_Url(self::API_URL . '/findNearbyPoastalCodesJSON');
        $url->add(array(
            'lat' => $vars->lat,
            'lng' => $vars->lon
        ));

        $result = $this->_doRequest($url);

        return new Horde_Core_Ajax_Response_Prototypejs(array(
            'results' => $result->getBody(),
            'status' => $result->code
        ));
    }

    protected function _doRequest(Horde_Url $url)
    {
        global $conf, $injector;

        $url->add(array(
            'username' => $conf['api']['geonames']
        ));

        return $injector->getInstance('Horde_Core_Factory_HttpClient')
            ->create()
            ->get($url);
    }

}
