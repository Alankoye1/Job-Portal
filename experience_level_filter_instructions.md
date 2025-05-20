# Implementing Experience Level Filtering for Jobs

## Issue Fixed
The error message `Unknown column 'j.experience_level' in 'where clause'` was occurring because the code in `browse-jobs.php` was filtering by an `experience_level` column that doesn't exist in the `jobs` table.

## Current Status
- The filter has been temporarily disabled in the UI
- The SQL filtering code has been commented out to prevent the error

## How to Implement Experience Level Filtering

Follow these steps to implement the experience level filtering feature:

1. **Add the column to the database**
   - Run the provided SQL script `add_experience_level.sql`:
   ```
   mysql -u username -p job_portal < add_experience_level.sql
   ```
   - This will add the `experience_level` column to the `jobs` table and populate it with sample values

2. **Update the job creation and edit forms**
   - Add an experience level dropdown field to the employer's job creation form
   - Use the predefined experience levels from `getExperienceLevels()` function

3. **Re-enable the filter in `browse-jobs.php`**
   - Uncomment the filtering code (around line 55)
   ```php
   if (!empty($experience)) {
       $query .= " AND j.experience_level = ?";
       $count_query .= " AND j.experience_level = ?";
       $params[] = $experience;
       $types .= "s";
   }
   ```
   - Replace the disabled dropdown with the original experience level dropdown

4. **Update the job listing templates**
   - Add experience level display in job listings where relevant
   - Format: `<span class="badge bg-info">Entry Level</span>` (or similar)

5. **Testing**
   - Create several jobs with different experience levels
   - Verify the filtering works correctly on the browse jobs page

## Reference
The expected values for experience_level are:
- 'entry' - Entry Level 
- 'intermediate' - Intermediate
- 'experienced' - Experienced
- 'manager' - Manager
- 'director' - Director 
- 'executive' - Executive 