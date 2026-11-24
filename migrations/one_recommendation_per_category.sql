-- Keep the most recently added recommendation for each category.
DELETE older FROM budget_recommendations AS older
JOIN budget_recommendations AS newer
  ON newer.category_id = older.category_id
 AND newer.recommendation_id > older.recommendation_id;

ALTER TABLE budget_recommendations
    ADD UNIQUE INDEX IF NOT EXISTS uq_recommendation_category (category_id);
