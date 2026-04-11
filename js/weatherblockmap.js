var WeatherBlockMap = {
    maps: {},

    initializeMap: function(instance, point)
    {
        var layers = [], p = new HordeMap.Owm(), map;

        Object.values(p.getLayers()).forEach(function(e) {
            if (e.name == 'OpenWeatherMap Wind Map') {
                e.visibility = false;
            }
            layers.push(e);
        });
        p = new HordeMap.Osm();
        Object.values(p.getLayers()).forEach(function(e) {
            e.displayInLayerSwitcher = false;
            layers.push(e);
        });

        map = new HordeMap.Map['Horde']({
            elt: 'weathermaplayer_' + instance,
            layers: layers,
            panzoom: false
        });

        var mapEl = document.getElementById('weathermaplayer_' + instance);
        var container = mapEl.parentElement.parentElement;
        mapEl.style.top = '0';
        mapEl.style.width = ((container.offsetWidth / 2) + 10) + 'px';
        mapEl.style.height = container.offsetHeight + 'px';
        map.updateMapSize();
        map.setCenter(point, 7);
        map.display();
        WeatherBlockMap.maps[instance] = map;
    }
};
