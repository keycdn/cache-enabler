jQuery(document).ready(
    function($) {

        $('.edit-cache-action', '#misc-publishing-actions').click(
            function(e) {
                $(this)
                    .next(':hidden')
                    .slideDown('fast')
                    .end()
                    .hide();

                e.preventDefault();
            }
        );

        $('.save-cache-action', '#misc-publishing-actions').click(
            function(e) {
                $(this)
                    .parent()
                    .slideUp('fast')
                    .prev(':hidden')
                    .show();

                $('#output-cache-action').text(
                    $('#cache_action').children('option:selected').text()
                );

                e.preventDefault();
            }
        );

        $('.cancel-cache-action', '#misc-publishing-actions').click(
            function(e) {
                $(this)
                    .parent()
                    .slideUp('fast')
                    .prev(':hidden')
                    .show();

                e.preventDefault();
            }
        );
    }
);
