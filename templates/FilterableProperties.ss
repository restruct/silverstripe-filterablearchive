<% if $HolderPage.ArchiveActive && not $ShowDateBelow %>
    <small class="text-body-secondary fst-italic me-2">
        <% if $DateField.Time %>$DateField.Format('d MMMM yyyy H:mm')<% else %>$DateField.Format('d MMMM yyyy')<% end_if %>
        $DateFieldComment
    </small>
<% end_if %>
<% if $HolderPage.CategoriesActive %>
<% loop $Categories %>
    <small class="badge fw-normal text-bg-light text-muted border">
    <% if $Up.LinkFilterProps %><a href="$Link" class="text-decoration-none"><% end_if %>
    $Title
    <% if $Up.LinkFilterProps %></a><% end_if %>
    </small>
<% end_loop %>
<% end_if %>
<% if $HolderPage.TagsActive %>
<% loop $Tags %>
    <small class="badge rounded-pill fw-normal text-bg-light text-muted border">
    <% if $Up.LinkFilterProps %><a href="$Link" class="text-decoration-none"><% end_if %>
    $Title
    <% if $Up.LinkFilterProps %></a><% end_if %>
    </small>
<% end_loop %>
<% end_if %>
<% if $HolderPage.ArchiveActive && $ShowDateBelow %>
    <small class="text-body-secondary fst-italic mt-2 d-block">$DateField.Format('d MMMM yyyy')</small>
<% end_if %>
