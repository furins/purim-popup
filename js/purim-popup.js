jQuery(document).ready(function ($) {

    function createOverlay() {
        if ($('#purim-popup-overlay').length) return;

        $('body').append(`
            <div id="purim-popup-overlay">
                <div id="purim-popup-container">
                    <div id="purim-popup-content">
                        <button id="purim-popup-close" type="button" aria-label="Chiudi popup">&times;</button>
                        <div class="purim-popup-inner"></div>
                    </div>
                </div>
            </div>
        `);

        $('#purim-popup-overlay').on('click', function (e) {
            if ($(e.target).closest('#purim-popup-content').length === 0) {
                closePopup();
            }
        });
    }

    function closePopup() {
        $('#purim-popup-overlay').fadeOut(200, function () {
            $('#purim-popup-overlay .purim-popup-inner').empty();
        });
    }

    function openPopup(id) {
        if (!id) return;

        createOverlay();

        $.post(PurimPopup.ajax_url, {
            action: 'purim_get_popup',
            id: id,
            nonce: PurimPopup.nonce
        }, function (response) {
            if (response && response.success) {
                $('#purim-popup-overlay .purim-popup-inner').html(
                    `<h2>${response.data.title}</h2>${response.data.content}`
                );
                $('#purim-popup-overlay').fadeIn(200);
            } else if (response && response.data) {
                window.alert(response.data);
            }
        }).fail(function () {
            window.alert('Impossibile caricare il contenuto del popup.');
        });
    }

    $('body').on('click', '#purim-popup-close', function (e) {
        e.preventDefault();
        closePopup();
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closePopup();
        }
    });

    $('body').on('click', '.purim-popup-trigger, .purim-image-hotspot', function (e) {
        e.preventDefault();
        const id = $(this).data('popup-id');
        openPopup(parseInt(id, 10) || 0);
    });
});
