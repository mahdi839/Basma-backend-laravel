📌 Project Overview

Basma Backend is a production-ready Laravel REST API built to power a complete e-commerce platform.
The project focuses on scalable backend architecture, complex query handling, third-party API integrations, and data-driven admin dashboards.

This repository demonstrates my experience building real-world Laravel systems, including product management, order processing, customer analytics, advanced filtering, and courier/payment integrations.

🚀 Core Features
🔗 Third-Party API Integrations

Pathao API — Courier service integration for delivery handling

Steadfast Courier API — Shipment creation, tracking, and logistics management

Robust error handling and configurable environment-based API settings

📦 Product Management API

Create, update, delete, and list products

Advanced filtering:

Search by product name

Filter by SKU

Filter by status

Paginated responses for performance

Clean validation and API responses

🗂 Category & Size Management

Category CRUD APIs

Size / variant management APIs

Designed to support flexible product variations

🛒 Order Management System

Create and manage customer orders

Order data includes:

Customer name & phone

District & address

Ordered products with:

Quantity

Price

Selected size

Color-based product images

Admin order printing support

Optimized structure for courier handoff and invoice generation

📊 Advanced Admin Dashboard (Analytics & Filters)
🔍 Robust Order & Customer Filtering

Search by:

Customer name

Phone number

Filter by:

District

Date range

Sorting:

Highest spent customers

Lowest spent customers

🏆 Customer Leaderboard System

A fully custom leaderboard & analytics module, built using optimized SQL queries.

Implemented Features:

Group customers by phone number

Calculate:

Total orders per customer

Total amount spent

First & last order dates

Dynamic sorting:

Order count (ASC / DESC)

Total spent (ASC / DESC)

Customer badges:

new customer

repeat_customer

Enriched leaderboard data:

Last ordered products

Product quantity, size, color image, pricing

Paginated leaderboard results for large datasets

📈 Customer Statistics API

Total customers

Total orders

Total revenue

Average order value

New vs repeat customer breakdown

🧑‍💻 Frontend-Driven API Support

The backend fully supports frontend features such as:

Shopping cart system

Slot-based ordering system

Product search system

Shop page with filters:

Price range

Categories

Variants (size, color)

Optimized APIs for React / Next.js frontend consumption

🛠 Tech Stack

Backend Framework: Laravel

Language: PHP

Database: MySQL

Authentication: Token-based (Laravel Sanctum)

External APIs: Pathao, Steadfast Courier

Tools: Composer, Artisan, PHPUnit
