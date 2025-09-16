# Ecocash Open API PHP Client

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://www.php.net/)  
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)  
[![Status](https://img.shields.io/badge/stability-stable-brightgreen.svg)]()  

A simple, dependency-free PHP library to integrate directly with **Ecocash Open API** for:

- 💰 **Payments (C2B Instant)**  
- 💸 **Refunds (C2B Instant)**  
- 🔍 **Transaction Lookup**  

---

## ✨ Features

- ✅ **PSR-4 compatible**  
- ✅ Works with **PHP 8.1**  
- ✅ Uses **cURL internally** (no external dependencies)  
- ✅ Supports both **Sandbox** and **Live** environments  
- ✅ Built-in **UUID generator**  
- ✅ Helper for **MSISDN (mobile number) normalization**  
- ✅ Structured **exception handling**  

---

## 📋 Requirements

- PHP **7.4 or higher**  
- cURL enabled in PHP (`ext-curl`)  
- An **Ecocash API Key** (get from [Ecocash Developer Portal](https://developers.ecocash.co.zw/))  

---

## 📦 Installation

You can install it via **Composer** or include it directly in your project.

## Option 1: Composer (recommended)
```bash
composer require your-namespace/ecocash-client

---


