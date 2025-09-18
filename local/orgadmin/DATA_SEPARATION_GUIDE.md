# Complete Data Separation Implementation Guide

## 🏗️ Architecture Overview

This system implements **complete data separation** between Site-Level roles and Organization-Level roles with **zero cross-access**.

## 📊 Data Separation Matrix

| Role Level | Context Level | Data Access | Cannot See |
|------------|---------------|-------------|------------|
| **Site Admin** | 10 (System) | Site users only | Organization data |
| **Site L&D** | 10 (System) | Site assessments only | Organization assessments |
| **Site Students** | 10 (System) | Site courses only | Organization courses |
| **Site Stakeholders** | 10 (System) | Site student heatmaps only | Organization students |
| **Org Admin** | 40 (Category) | Their org users only | Site data + Other orgs |
| **Org L&D** | 40 (Category) | Their org assessments only | Site data + Other orgs |
| **Org Students** | 40 (Category) | Their org courses only | Site data + Other orgs |
| **Org Stakeholders** | 40 (Category) | Their org heatmaps only | Site data + Other orgs |

## 🔐 Context Levels in Moodle

- **Context Level 10**: System/Site context - Global platform level
- **Context Level 40**: Category context - Organization level

## 🎯 Real-World Examples

### Example 1: Training Platform with Multiple Client Organizations

```
Site Level (Platform Owners)
├── Site Admin: Platform management
├── Site L&D: Platform-wide course quality
├── Site Students: Platform demo courses
└── Site Stakeholders: Platform performance metrics

Microsoft Organization (Client A)
├── Microsoft Org Admin: Manage Microsoft users
├── Microsoft L&D: Microsoft training programs
├── Microsoft Students: Microsoft employees
└── Microsoft Stakeholders: Microsoft performance only

Google Organization (Client B)
├── Google Org Admin: Manage Google users
├── Google L&D: Google training programs
├── Google Students: Google employees
└── Google Stakeholders: Google performance only
```

### Example 2: University System

```
Site Level (University Administration)
├── Site Admin: IT infrastructure management
├── Site L&D: Academic standards committee
├── Site Students: Cross-department programs
└── Site Stakeholders: University-wide metrics

Computer Science Department
├── CS Org Admin: Manage CS faculty/students
├── CS L&D: CS curriculum management
├── CS Students: CS department students
└── CS Stakeholders: CS performance metrics

Business School Department
├── Business Org Admin: Manage business faculty/students
├── Business L&D: Business curriculum management
├── Business Students: Business school students
└── Business Stakeholders: Business performance metrics
```

## 🛠️ Technical Implementation

### Database Structure

```sql
-- Assessment submissions with organization separation
CREATE TABLE local_orgadmin_assessment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id VARCHAR(255) NOT NULL,
    student_id INT NOT NULL,
    organization_id INT NULL, -- NULL for site-level, category_id for org-level
    code_solution TEXT,
    test_results JSON,
    score INT DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for data separation
    INDEX idx_organization_assessment (organization_id, assessment_id),
    INDEX idx_student_organization (student_id, organization_id),
    INDEX idx_site_level (organization_id) -- For site-level queries (WHERE organization_id IS NULL)
);
```

### Role Detection Logic

```php
// Site-level role detection
private static function is_site_level_role($user_id, $role_shortname) {
    // Check context level 10 (system context)
    return $DB->record_exists_sql("
        SELECT 1 FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ra.userid = ? AND r.shortname = ? AND ctx.contextlevel = 10
    ", [$user_id, $role_shortname]);
}

// Organization-level role detection
private static function is_organization_level_role($user_id, $role_shortname) {
    // Check context level 40 (category context)
    return $DB->record_exists_sql("
        SELECT 1 FROM {role_assignments} ra
        JOIN {role} r ON r.id = ra.roleid
        JOIN {context} ctx ON ctx.id = ra.contextid
        WHERE ra.userid = ? AND r.shortname = ? AND ctx.contextlevel = 40
    ", [$user_id, $role_shortname]);
}
```

### Data Query Examples

#### Site L&D Analytics (Site-Level Data Only)
```php
$students_sql = "
    SELECT COUNT(DISTINCT u.id) as total_submissions
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    JOIN {context} ctx ON ctx.id = ra.contextid
    WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
    AND r.shortname = 'student'
    AND ctx.contextlevel = 10  -- SITE LEVEL ONLY
";
```

#### Organization L&D Analytics (Organization-Specific Data Only)
```php
$students_sql = "
    SELECT COUNT(DISTINCT u.id) as total_submissions
    FROM {user} u
    JOIN {role_assignments} ra ON ra.userid = u.id
    JOIN {role} r ON r.id = ra.roleid
    JOIN {context} ctx ON ctx.id = ra.contextid
    WHERE u.deleted = 0 AND u.suspended = 0 AND u.confirmed = 1
    AND r.shortname = 'student'
    AND ctx.contextlevel = 40 AND ctx.instanceid = ?  -- SPECIFIC ORG ONLY
";
```

## 🚫 What Each Role CANNOT See

### Site Admin Dashboard
- ✅ Can see: Platform infrastructure, site-level users, system settings
- ❌ Cannot see: Organization A data, Organization B data, any org-specific metrics

### Organization A L&D Dashboard
- ✅ Can see: Organization A students, Organization A assessments
- ❌ Cannot see: Site-level data, Organization B data, Organization C data

### Site Student Dashboard
- ✅ Can see: Site-level courses, site-level assessments
- ❌ Cannot see: Organization courses, organization assessments

### Organization Stakeholder Dashboard
- ✅ Can see: Their organization's student heatmaps
- ❌ Cannot see: Site student data, other organization student data

## 🔄 Assessment Workflow with Data Separation

### Site-Level Workflow
```
Site Trainer → Site L&D → Site Students → Site Results → Site Stakeholders
(All at context level 10)
```

### Organization-Level Workflow
```
Org A Trainer → Org A L&D → Org A Students → Org A Results → Org A Stakeholders
(All at context level 40, category A)

Org B Trainer → Org B L&D → Org B Students → Org B Results → Org B Stakeholders
(All at context level 40, category B)
```

## ✅ Verification Checklist

- [ ] Site L&D cannot see organization assessments
- [ ] Organization L&D cannot see site assessments
- [ ] Site students cannot see organization courses
- [ ] Organization students cannot see site courses
- [ ] Site stakeholders cannot see organization student heatmaps
- [ ] Organization stakeholders cannot see site student heatmaps
- [ ] Organization A users cannot see Organization B data
- [ ] All data queries filter by context level (10 vs 40)
- [ ] Database indexes support efficient separation queries

This implementation ensures **complete data isolation** between all role levels and organizations.