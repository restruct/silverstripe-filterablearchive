<% if PaginatedItems.MoreThanOnePage %>
<nav class="pagination_container">
	<ul class="pagination">
		<li class="page-item <% if not $PaginatedItems.NotFirstPage %>disabled<% end_if %>">
			<a class="page-link" href="$PaginatedItems.PrevLink" aria-label="Previous">
				<span aria-hidden="true">&laquo;</span>
			</a>
		</li>
		<% loop $PaginatedItems.PaginationSummary(4) %>
		<li class="page-item <% if CurrentBool %>active<% end_if %>">
			<% if Link %>
				<a class="page-link" href="$Link" title="View page number $PageNum">$PageNum</a>
			<% else %>
				<span class="page-link pages">&hellip;</span>
			<% end_if %>
		</li>
		<% end_loop %>
		<li class="page-item <% if not PaginatedItems.NotLastPage %>disabled<% end_if %>">
			<a class="page-link" href="$PaginatedItems.NextLink" aria-label="Next">
				<span aria-hidden="true">&raquo;</span>
			</a>
		</li>
	</ul>
</nav>
<% end_if %>
