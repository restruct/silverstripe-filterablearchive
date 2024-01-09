<div class="container archivefilter filterable">
    <form class="row bg-body-secondary rounded py-2">
        <% if $CategoriesActive %>
        <% with $FilterDropdown('cat') %> <%-- optionally supply the dropdown 'label' (emptyString) as argument --%>
        <div class="col input-group cat-filter <% if $Up.FilteredCatSegment %>cat-filtering curr-cat-{$Up.FilteredCatSegment.ATT}<% end_if %>">
            $Me
            <% if $Up.FilteredCatSegment %>
            <a class="input-group-text text-decoration-none" href="$Up.Link" onclick="$UnsetAndSubmitOnClick">&times;</a>
            <% end_if %>
        </div>
        <% end_with %>
        <% end_if %>

        <% if $TagsActive %>
        <% with $FilterDropdown('tag') %> <%-- optionally supply the dropdown 'label' (emptyString) as argument --%>
        <div class="col input-group tag-filter" <% if $FilteredTagSegment %>tag-filtering curr-tag-{$FilteredTagSegment.ATT}<% end_if %>>
            $Me
            <% if $Up.FilteredTagSegment %>
            <a class="input-group-text text-decoration-none" href="$Up.Link" onclick="$UnsetAndSubmitOnClick">&times;</a>
            <% end_if %>
        </div>
        <% end_with %>
        <% end_if %>

        <% if $ArchiveActive %>
        <% with $ArchiveFilterDropdown() %> <%-- optionally supply the dropdown 'label' (emptyString) as argument --%>
        <div class="col input-group date-filter <% if $FilteredDate %>currently-filtering curr-date-{$FilteredDate.ATT}<% end_if %>">
             $Me
             <% if $Up.FilteredDate %>
             <a class="input-group-text text-decoration-none" href="$Up.Link" onclick="$UnsetAndSubmitOnClick">&times;</a>
             <% end_if %>
        </div>
        <% end_with %>
        <% end_if %>
    </form>
</div>
