<article class="$Classes('rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200')"$Attributes>
    <% if $Title %><h3 class="text-lg font-semibold text-gray-900">$Title</h3><% end_if %>
    <div class="prose prose-gray mt-2">$Children</div>
    <% if $HasSlot('footer') %>
        <footer class="mt-4 border-t border-gray-200 pt-4">$Slot('footer')</footer>
    <% end_if %>
</article>
