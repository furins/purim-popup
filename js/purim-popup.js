jQuery(document).ready(function ($) {

    function createOverlay() {
        if ($('#purim-popup-overlay').length) return;

        $('body').append(`
            <div id="purim-popup-overlay">
                <div id="purim-popup-container">
                    <div id="purim-popup-content">
                        <button id="purim-popup-close">&times;</button>
                        <div class="purim-popup-inner"></div>
                    </div>
                </div>
            </div>
        `);

        $('#purim-popup-close, #purim-popup-overlay').on('click', function (e) {
            if (e.target.id === 'purim-popup-overlay' || e.target.id === 'purim-popup-close') {
                $('#purim-popup-overlay').fadeOut(200);
            }
        });
    }

    $('body').on('click', '.purim-popup-trigger', function (e) {
        e.preventDefault();
        createOverlay();

        let id = $(this).data('popup-id');

        $.post(PurimPopup.ajax_url, {
            action: 'purim_get_popup',
            id: id
        }, function (response) {
            if (response.success) {
                $('#purim-popup-overlay .purim-popup-inner').html(
                    `<h2>${response.data.title}</h2>${response.data.content}`
                );
                $('#purim-popup-overlay').fadeIn(200);
            } else {
                alert(response.data);
            }
        });
    });
});
