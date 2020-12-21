<% if PaginatedItems.MoreThanOnePage %>
<nav class="pagination_container">
	<ul class="pagination">
		<li class="<% if not PaginatedItems.NotFirstPage %>disabled<% end_if %>">
			<a href="$PaginatedItems.PrevLink" aria-label="Previous">
				<span aria-hidden="true">&laquo;</span>
			</a>
		</li>
		<% loop $PaginatedItems.PaginationSummary(4) %>
		<li class="<% if CurrentBool %>active<% end_if %>">
			<% if Link %>
				<a href="$Link" title="View page number $PageNum">$PageNum</a>
			<% else %>
				<span class="pages">&hellip;</span>
			<% end_if %>
		</li>
		<% end_loop %>
		<li class="<% if not PaginatedItems.NotLastPage %>disabled<% end_if %>">
			<a href="$PaginatedItems.NextLink" aria-label="Next">
				<span aria-hidden="true">&raquo;</span>
			</a>
		</li>
	</ul>
</nav>
<% end_if %>