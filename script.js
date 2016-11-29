jQuery(function () {
    /**
     * Attach the click handler
     */
    jQuery('.structstatus-full.editable').find('div.struct_status')
        .click(function () {
            var $self = jQuery(this);
            $self.parent().css('visibility', 'hidden');

            var set = makeDataSet($self.parent(), $self.data('pid'));

            var data = {
                sectok: $self.parent().data('st'),
                field: $self.parent().data('field'),
                pid: $self.parent().data('page'),
                entry: set,
                call: 'plugin_struct_inline_save'
            };

            jQuery.post(
                DOKU_BASE + 'lib/exe/ajax.php',
                data
            )
                .error(function (jqXHR) {
                    alert(jqXHR.responseText);
                })
                .success(function (response) {
                    applyDataSet($self.parent(), set);
                    jQuery('#plugin__struct_output').find('td[data-struct="'+ $self.parent().data('field') +'"]').html(response);
                })
                .done(function () {
                    $self.parent().css('visibility', 'visible');
                })
            ;

        })
        .css('cursor', 'pointer')
    ;

    /**
     * Set the statuses accrding to the given set
     *
     * @param {jQuery} $full the wrapper around the statuses
     * @param {int|int[]} set the status or the list of statuses to enable
     */
    function applyDataSet($full, set) {
        $full.find('div.struct_status').each(function () {
            if(typeof set == 'number') {
                set = [set];
            }
            var $self = jQuery(this);
            if (set.indexOf($self.data('pid')) === -1) {
                $self.addClass('disabled');
            } else {
                $self.removeClass('disabled');
            }
        });
    }

    /**
     * Create a set based on the current set and the status to toggle
     *
     * @param {jQuery} $full the wrapper around the statuses
     * @param {int} toggle the pid of the status to toggle
     * @return {int|int[]} the resulting new set
     */
    function makeDataSet($full, toggle) {
        var set = [];
        var wason = false;

        $full.find('div.struct_status').not('.disabled').each(function () {
            var pid = jQuery(this).data('pid');
            if (pid === toggle) {
                wason = true; // this is the value we're toggling
            } else {
                set.push(pid); // this is an enabled value we keep
            }
        });
        if (!wason) {
            set.push(toggle); // value was not enabled previously, we toggle it
        }

        // for non-multi field only one value is allowed
        if (!$full.data('multi')) {
            return set.pop();
        }
        return set;
    }


});
