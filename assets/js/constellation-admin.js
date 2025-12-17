/**
 * Constellation Admin JavaScript
 */

(function($) {
    'use strict';

    /**
     * Initialize stat card click handlers
     */
    function initStatCards() {
        $(document).on('click', '.mosaic-stat-card-clickable', function(e) {
            var href = $(this).data('href');
            if (href) {
                window.location.href = href;
            }
        });
    }

    /**
     * Initialize delete confirmations using Mosaic dialog
     */
    function initDeleteConfirmations() {
        $(document).on('click', '.mosaic-confirm-delete', function(e) {
            e.preventDefault();
            var $link = $(this);
            var href = $link.attr('href');
            var message = $link.data('confirm') || 'Are you sure you want to delete this item?';

            Mosaic.confirmDanger(message, { title: 'Delete' }).then(function(confirmed) {
                if (confirmed) {
                    window.location.href = href;
                }
            });

            return false;
        });
    }

    /**
     * Initialize color picker
     */
    function initColorPicker() {
        $(document).on('change', '.constellation-color-option input[type="radio"]', function() {
            $(this).closest('.constellation-color-picker')
                .find('.constellation-color-option')
                .removeClass('selected');
            $(this).closest('.constellation-color-option').addClass('selected');
        });
    }

    /**
     * Initialize search handlers
     */
    function initSearch() {
        var $searchInput = $('.mosaic-search-box input[type="search"]');
        var originalValue = $searchInput.val() || '';

        // Auto-search on blur if value changed
        $searchInput.on('blur', function() {
            var $input = $(this);
            var newValue = $input.val();

            if (newValue !== originalValue) {
                if (newValue === '') {
                    // Clear search
                    var url = new URL(window.location.href);
                    url.searchParams.delete('search');
                    window.location.href = url.toString();
                } else {
                    // Submit the form
                    $input.closest('form').submit();
                }
            }
        });

        // Clear search when input is emptied
        $searchInput.on('input', function() {
            var $input = $(this);
            if ($input.val() === '' && originalValue !== '') {
                var url = new URL(window.location.href);
                url.searchParams.delete('search');
                window.location.href = url.toString();
            }
        });
    }

    /**
     * Initialize tag modal
     */
    function initTagModal() {
        var $modalContent = $('#constellation-tag-modal');
        if (!$modalContent.length) return;

        // Add tag button
        $(document).on('click', '.constellation-add-tag-btn', function(e) {
            e.preventDefault();
            openTagModal(null, null, null, null);
        });

        // Edit tag button - intercept clicks on Edit buttons in tags table
        $(document).on('click', '.mosaic-table a.mosaic-btn-primary[href="#"]', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            var tagId = $row.data('id');
            var tagName = $row.find('.constellation-tag').text();
            var tagDescription = $row.data('description') || '';
            var tagColor = '';

            // Extract color from style
            var style = $row.find('.constellation-tag').attr('style');
            if (style) {
                var match = style.match(/background-color:\s*([^;]+)/);
                if (match) tagColor = match[1].trim();
            }

            openTagModal(tagId, tagName, tagDescription, tagColor);
        });
    }

    /**
     * Open tag modal
     */
    function openTagModal(tagId, tagName, tagDescription, tagColor) {
        var $content = $('#constellation-tag-modal').clone();
        $content.removeAttr('id').show();

        var isEdit = !!tagId;
        var title = isEdit ? 'Edit Tag' : 'Add Tag';

        // Set form values
        if (isEdit) {
            $content.find('#tag-id').val(tagId);
            $content.find('#tag-name').val(tagName);
            $content.find('#tag-description').val(tagDescription);
            $content.find('input[name="tag_color"][value="' + tagColor + '"]').prop('checked', true);
            $content.find('input[name="tag_color"][value="' + tagColor + '"]').closest('.constellation-color-option').addClass('selected');
        } else {
            // Select first color by default
            $content.find('input[name="tag_color"]').first().prop('checked', true);
            $content.find('.constellation-color-option').first().addClass('selected');
        }

        // Add buttons to the form
        var $form = $content.find('form');
        $form.append('<div class="mosaic-modal-actions" style="margin-top: 20px; display: flex; gap: 8px; justify-content: flex-end;">' +
            '<button type="button" class="mosaic-btn mosaic-btn-secondary constellation-modal-cancel">Cancel</button>' +
            '<button type="submit" class="mosaic-btn mosaic-btn-primary">Save</button>' +
            '</div>');

        Mosaic.modal({
            title: title,
            content: $content[0],
            buttons: [],
            onOpen: function(modal, body) {
                // Focus name input
                $(body).find('#tag-name').focus();

                // Handle color picker clicks
                $(body).on('click', '.constellation-color-option', function() {
                    $(body).find('.constellation-color-option').removeClass('selected');
                    $(this).addClass('selected');
                    $(this).find('input').prop('checked', true);
                });

                // Handle cancel button
                $(body).on('click', '.constellation-modal-cancel', function() {
                    Mosaic.closeModal();
                });
            }
        });
    }

    /**
     * Initialize on document ready
     */
    $(function() {
        initStatCards();
        initDeleteConfirmations();
        initColorPicker();
        initSearch();
        initTagModal();
    });

})(jQuery);
