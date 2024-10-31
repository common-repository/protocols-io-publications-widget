var pickr = [];

var WidgetAction = function() {
    $('.protocolsio-widget-type').on('change', function () {
        var value = $(this).find(':selected').val();
        var toggleElm = $('#' + $(this).data('toggle-id'));

        if (value === 'user' || value === 'group') {
            toggleElm.parent().show('fast');
        } else {
            toggleElm.parent().hide('fast');
        }
    });

    $('.protocolsio-widget-reset').on('click', function (event) {
        event.preventDefault();

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: ajax_object.ajax_url,
            data: {
                'action': 'protocolsio_reset_transient',
                'type': $(this).attr("data-type"),
                'username': $(this).attr("data-username")
            }
        });
    });

    $('.protocolio-widget-advanced-toggle').on('click', function (event) {
        event.preventDefault();

        var link = $(this);

        $(this).parent().find('.protocolio-widget-advanced-block').toggle('fast', function () {
            if ($(this).is(":visible")) {
                link.find('span').html('<span class="dashicons dashicons-arrow-up"></span>');
            } else {
                link.find('span').html('<span class="dashicons dashicons-arrow-down"></span>');
            }
        });
    });
}

var PickerLoad = function() {
    [].forEach.call(
        document.querySelectorAll('.protocolsio-widget-color-picker'),
        function (el, index) {
            var originalColor = $(el).parent().find('.protocolsio-color-input').val();
            pickr[index] = Pickr.create({
                el: el,
                default: originalColor,
                components: {
                    preview: false,
                    opacity: false,
                    hue: true,
                    interaction: {
                        hex: true,
                        rgba: false,
                        hsla: false,
                        hsva: false,
                        cmyk: false,
                        input: true,
                        clear: false,
                        save: true
                    }
                },
                onSave(hsva, instance) {
                    var color = hsva.toHEX().toString();
                    $(instance.getRoot().root).parent().find('.protocolsio-color-input').val(color).trigger('change');
                },
            });
        }
    );
}

jQuery(document).ready(function ($) {
    PickerLoad();

    WidgetAction();

    $(document).on('widget-added widget-updated', function() {
        PickerLoad();
        WidgetAction();
    })
});