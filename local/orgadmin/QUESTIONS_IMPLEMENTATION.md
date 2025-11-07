# Questions Implementation Summary

## Overview
This document describes the implementation of the new question management system in the teacher dashboard.

## Database Changes

### 1. Updated `install.xml` (local/orgadmin/db/install.xml)
Added four tables:
- **orgadmin_assessments**: Teacher-created assessments (existing table now in install.xml)
- **local_questions**: Stores questions with QID, title, expectation, programming language, and max marks
- **local_question_details**: Stores question details/criteria with QDetailID, title, and max marks per detail
- **local_question_scores**: Stores student scores per criterion per attempt

### 2. Database Schema

#### local_questions table
```sql
- id (auto-increment)
- assessment_id (FK to orgadmin_assessments)
- qid (unique identifier like Q1, Q2)
- qtitle (question title)
- expectation (text description)
- programming_language (java, python, etc.)
- max_marks (calculated from details)
- timecreated
- timemodified
```

#### local_question_details table
```sql
- id (auto-increment)
- question_id (FK to local_questions)
- qdetailid (optional detail identifier like D1, D2)
- qdetailtitle (detail/criteria title)
- max_marks (marks for this criterion)
- sortorder (order of display)
```

#### local_question_scores table
```sql
- id (auto-increment)
- question_id (FK to local_questions)
- detail_id (FK to local_question_details)
- userid (FK to mdl_user)
- marks (score awarded)
- attempt_no (attempt number)
- timecreated
```

## UI Changes

### Teacher Dashboard (local/orgadmin/teacher_dashboard.php)

#### Removed Fields
- **Duration field** - Removed from assessment details section

#### Added Fields
1. **Questions Section** with "Add Question" button (green button with + icon)

For each question:
- Question ID (QID) - e.g., Q1, Q2
- Question Title
- Programming Language dropdown (Java, Python, JavaScript, C++, C#, PHP, Ruby, Go)
- Expectation (textarea)
- Question Details subsection with "Add Detail" button

For each question detail:
- Detail ID (optional) - e.g., D1, D2
- Detail Title (required)
- Description
- Max Marks (required)
- Remove button (X icon)

#### UI Features
- Dynamic addition/removal of questions
- Dynamic addition/removal of question details
- Each question auto-initializes with one detail field
- Validation ensures at least one question exists before submission
- Responsive card-based layout with clear visual hierarchy

## Backend Changes

### assessment_handler.php

#### New Function: `save_assessment_questions()`
```php
function save_assessment_questions($assessment_id, $questions, $time, $courseid = 0)
```
- Saves questions and their details to the database
- Calculates total max_marks from details
- Maintains proper relationships between tables
- Handles data sanitization

#### Updated Cases
1. **save_draft**: Now accepts and saves questions array
2. **submit_review**: Now accepts and saves questions array
3. Both cases delete existing questions when updating an assessment

## How to Use

### Creating an Assessment with Questions

1. Click "Add New Assessment" button
2. Fill in assessment details (title, total marks, pass percentage, etc.)
3. The form automatically creates one question card
4. Fill in question details:
   - Enter QID (e.g., Q1)
   - Enter question title
   - Select programming language
   - Describe expectations
5. Add question details/criteria:
   - Each question has at least one detail
   - Click "+ Add Detail" to add more criteria
   - Fill in detail title and max marks
6. Click "+ Add Question" to add more questions
7. Save as draft or submit for review

### Data Flow
1. User fills form → JavaScript collects all data
2. Form data sent via AJAX to assessment_handler.php
3. Backend validates and saves to orgadmin_assessments
4. Backend calls save_assessment_questions() to save questions and details
5. Success message returned to user

## Installation Instructions

1. Navigate to Site Administration > Notifications
2. Moodle will detect the new install.xml changes
3. Click "Upgrade Moodle database now"
4. The new tables will be created automatically

Alternatively, you can manually run the SQL in phpMyAdmin (tables already created as per your requirement).

## Testing

1. Log in as a teacher/trainer
2. Navigate to teacher dashboard
3. Click "Add New Assessment"
4. Add questions with details
5. Save as draft
6. Verify data is saved in database:
   - Check orgadmin_assessments table
   - Check local_questions table
   - Check local_question_details table

## Notes

- Duration field removed as requested
- QID is unique across the system
- Max marks for a question is automatically calculated from its details
- Questions are linked to assessments via assessment_id
- Question details are linked to questions via question_id
- The system supports multiple attempts per student (tracked in local_question_scores)
