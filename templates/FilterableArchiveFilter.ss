<div class="container archivefilter filterable">
    <form class="row bg-body-secondary rounded py-2">
        <% if $CategoriesActive %>
        <div class="col cat-filter">
            $FilterDropdown('cat') <%-- optionally supply the dropdown 'label' (emptyString) as second argument --%>
        </div>
        <% end_if %>
        <% if $TagsActive %>
        <div class="col tag-filter">
            $FilterDropdown('tag') <%-- optionally supply the dropdown 'label' (emptyString) as second argument --%>
        </div>
        <% end_if %>
        <% if $ArchiveActive %>
        <div class="col date-filter">
            $ArchiveFilterDropdown() <%-- optionally supply the dropdown 'label' (emptyString) as argument --%>
        </div>
        <% end_if %>
    </form>
</div>
