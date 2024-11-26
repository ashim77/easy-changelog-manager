jQuery(document).ready(function($) {
    // Add new version entry
    $('#add-changelog-version').on('click', function() {
        var template = $('#version-entry-template').html();
        var index = $('#versions-list .changelog-version-entry').length;

        // Get current date in YYYY-MM-DD format
        var currentDate = new Date().toISOString().split('T')[0];
        
        // Replace index placeholder
        template = template.replace(/{{INDEX}}/g, index);
        // template = template.replace('value=""', 'value="' + currentDate + '"');
        
        $('#versions-list').append(template);
    });

    // Remove version entry
    $(document).on('click', '.remove-version-entry', function() {
        $(this).closest('.changelog-version-entry').remove();
    });

    // Add changelog entry to a specific version
    $(document).on('click', '.add-changelog-entry', function() {
        var versionIndex = $(this).data('version-index');
        var entryTemplate = `
            <div class="changelog-entry" data-version-index="${versionIndex}">
                <select name="changelog_type[${versionIndex}][]">
                    <option value="Added">Added</option>
                    <option value="Fixed">Fixed</option>
                    <option value="Changed">Changed</option>
                    <option value="Deprecated">Deprecated</option>
                    <option value="Removed">Removed</option>
                    <option value="Security">Security</option>
                </select>
                <input 
                    type="text" 
                    name="changelog_description[${versionIndex}][]" 
                    placeholder="Enter changelog description"
                >
                <button type="button" class="remove-changelog-entry button">Remove</button>
            </div>
        `;
        
        $(this).before(entryTemplate);
    });

    // Remove changelog entry
    $(document).on('click', '.remove-changelog-entry', function() {
        $(this).closest('.changelog-entry').remove();
    });
});