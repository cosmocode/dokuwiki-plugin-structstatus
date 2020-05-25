jQuery(function () {
    /**
     * Attach the click handler
     */
    jQuery('.structstatus-full.editable').find('button.struct_status')
        .click(function () {
            const $self = jQuery(this);
            $self.parent().css('visibility', 'hidden');

            const set = makeDataSet($self.parent(), $self.data('rid'));

            const data = {
                sectok: $self.parent().data('st'),
                field: $self.parent().data('field'),
                pid: $self.parent().data('page'),
                rid: $self.parent().data('rid'),
                rev: $self.parent().data('rev'),
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
                    const value = JSON.parse(response).value;
                    applyDataSet($self.parent(), set);
                    jQuery('#plugin__struct_output').find('td[data-struct="' + $self.parent().data('field') + '"]').html(value);
                })
                .done(function () {
                    $self.parent().css('visibility', 'visible');
                })
            ;

        })
    ;

    /**
     * Set the statuses according to the given set
     *
     * @param {jQuery} $full the wrapper around the statuses
     * @param {Array} set the status or the list of statuses to enable
     */
    function applyDataSet($full, set) {
        $full.find('button.struct_status').each(function () {
            if (typeof set == 'undefined') {
                set = [];
            }
            const $self = jQuery(this);
            if (set.indexOf(JSON.stringify($self.data('rid'))) === -1) {
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
     * @param {[]} toggle the rid of the status to toggle
     * @return {Array} the resulting new set
     */
    function makeDataSet($full, toggle) {
        const set = [];
        let wason = false;

        $full.find('button.struct_status').not('.disabled').each(function () {
            const rid = jQuery(this).data('rid');
            if (rid === toggle) {
                wason = true; // this is the value we're toggling
            } else {
                set.push(rid); // this is an enabled value we keep
            }
        });
        if (!wason) {
            set.push(toggle); // value was not enabled previously, we toggle it
        }

        // for non-multi field only one value is allowed
        if (!$full.data('multi')) {
            return [JSON.stringify(set.pop())];
        }

        return set.map(function (entry) {
            return JSON.stringify(entry);
        });
    }
});
