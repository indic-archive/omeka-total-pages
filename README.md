# Omeka item set total pages

This module adds or updates the `dcterms:extent` (eg: pages in a book) on an item set based on the sum of values (field `dcterms:extent`) of all items in it. The number is computed and updated when items are created, updated, and added and removed from an item set. This only works when there are summable numbers in the property.

This can be used to display the total number of pages in an item set (collection). [See example](https://gpura.org/collections/gundert-legacy-documents).
