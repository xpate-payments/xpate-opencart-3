$(document).ready(function () {
    var counter = 0;
    var loop = setInterval(
        function refresh_pending() {
            counter++;
            $.ajax({
                type: "POST",
                url: $(location).attr('href'),
                data: {processing: '1'},
                dataType: 'json',
                success: function (data) {
                    if (data.redirect == true) {
                        location.href = callback_url;
                    }
                }
            });
            if (counter >= 6) {
                clearInterval(loop);
                location.href = fallback_url;
            }
        },
        10000
    );
});
