jQuery(document).ready(function() {
    let ss_ids = dtStatic.nav_ids

    if( ! window.location.hash ) {
        let top_id = ss_ids[0]
        load_static_section_content( top_id.replace('#', '') )
    } else {
        load_static_section_content( window.location.hash )
    }
console.log(window.location.hash)

})
function load_static_section_content( id ) {
    "use strict";
    let chartDiv = jQuery('#chart')

    jQuery.ajax({
        type: 'POST',
        contentType: "application/json; charset=utf-8",
        data: JSON.stringify( { 'id': id } ),
        dataType: "json",
        url: dtStatic.root + 'static-section/v1/content',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-WP-Nonce', dtStatic.nonce);
        },
    })
        .done( function( response ) {
            chartDiv.empty().append(`
            ${response}
            `)

        }) // end success statement
        .fail(function (err) {
            console.log("error")
            console.log(err)
        })


}