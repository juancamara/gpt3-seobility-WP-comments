jQuery(document).ready(function ($) {

    $('.screenevolution-form').on('click', '.screenevolutionTogglePassword', function (e) {
        let password = $(this).prev('input'),
            type = password.prop('type') === 'password' ? 'text' : 'password';

        password.prop('type', type);

        $(this).toggleClass('dashicons-visibility dashicons-hidden');

    });

    $('.screenevolution-wrapper').on('click', '#seobility-openai', function (e) {
        e.preventDefault();
        $('.screenevolution-wrapper .button-holder').addClass('loading');
        $('.screenevolution-wrapper #seobility-openai').attr('disabled', 'disabled');
        $('.screenevolution-wrapper .status').text('');


        $.ajax({
            url: screenevolution.ajaxurl,
            data: {
                'action': screenevolution.action,
                'security': $('.screenevolution-wrapper input[name="security"]').val(),
                'post_id': screenevolution.post_id
            },
            type: 'POST',
            beforeSend: function () {
                $('.screenevolution-wrapper #seobility-openai').attr('disabled', 'disabled');
            },
            success: function (response) {
                $('.screenevolution-wrapper .status').text(response.data.message);

                if (response.data.comment) {
                    $('textarea#comment').val(response.data.comment);
                }
            },
            complete: function () {
                $('.screenevolution-wrapper #seobility-openai').removeAttr('disabled');
                $('.screenevolution-wrapper .button-holder').removeClass('loading');
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $('.screenevolution-wrapper .status').text(errorThrown);
                $('.screenevolution-wrapper #seobility-openai').removeAttr('disabled');
                $('.screenevolution-wrapper .button-holder').removeClass('loading');
            }
        });
    });
});
