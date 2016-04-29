# Magento_AutoRelate
Automatically adds random *Related Products* (from the predefined category map) upon saving a product.

## Example use case
When saving a product from the *Tops* category, creates four relations from the *Skirts* category.

## Configuration
Edit `app/code/local/Fire/AutoRelate/Model/Observer.php`:
 - `MAX_RELATED_PRODUCTS` controls the number of Related Products that will be added for each product
 - `getCategoryRelationMap()` must return an associative array in the form of `<category ID> => <relations category ID>`.
