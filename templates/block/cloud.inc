<script type="text/javascript">
function doSearch(tagName)
{
    var ajaxurl = '<?php echo Horde::url('services/portal/cloud_search.php', true)?>';

    document.getElementById('cloudloadingimg').style.display = '';
    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: new URLSearchParams({ tag: tagName }).toString(),
        credentials: 'same-origin'
    }).then(function(response) {
        return response.text();
    }).then(function(text) {
        var results = document.getElementById('cloudsearch');
        results.innerHTML = text;
        results.style.display = '';
        document.getElementById('cloudloadingimg').style.display = 'none';
    }).catch(function() {
        document.getElementById('cloudloadingimg').style.display = 'none';
    });
}
</script>
