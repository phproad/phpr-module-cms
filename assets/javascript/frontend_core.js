/**
 * Helpers
 */

function root_url(url) {
	if (typeof root_dir === 'undefined' || !root_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1);
	
	return root_dir + url;
}

function asset_url(url) {
	if (typeof asset_dir === 'undefined' || !asset_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1);
	
	return root_url(asset_dir + url);
}

function var_dump(obj, use_alert) {
    var out = '';
    for (var i in obj) {
        out += i + ": " + obj[i] + "\n";
    }

    if (use_alert)
        alert(out);
    else 
        jQuery('<pre />').html(out).appendTo(jQuery('body'));
    
};
