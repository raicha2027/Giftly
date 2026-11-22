
import os
import re
from pathlib import Path

import pandas as pd
import pymysql


# ---------------------------------------------------------
# DATABASE CONNECTION
# ---------------------------------------------------------

PROJECT_ROOT = Path(__file__).resolve().parent.parent


def get_connection():
    return pymysql.connect(
        host="localhost",
        user="root",
        password="",
        database="giftly",
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor
    )


# ---------------------------------------------------------
# GENERAL HELPERS
# ---------------------------------------------------------

def normalise(value):
    """Convert a value to lowercase searchable text."""
    if value is None:
        return ""

    return str(value).strip().lower()


def split_values(value):
    """
    Convert comma, pipe or semicolon separated database values
    into a list of lowercase values.
    """
    if value is None:
        return []

    if isinstance(value, list):
        raw_values = value
    else:
        raw_values = re.split(r"[,;|]", str(value))

    return [
        normalise(item)
        for item in raw_values
        if normalise(item)
    ]


def contains_term(text, term):
    """Check for a whole-word or phrase match."""
    text = normalise(text)
    term = normalise(term)

    if not text or not term:
        return False

    pattern = r"(?<![a-z0-9])" + re.escape(term) + r"(?![a-z0-9])"
    return re.search(pattern, text) is not None


def matches_preference(product_values, selected_value):
    """
    Match a selected preference against a product's saved values.
    Also supports a product having several comma-separated values.
    """
    selected = normalise(selected_value)

    if not selected:
        return True

    values = split_values(product_values)

    return any(
        selected == value
        or contains_term(value, selected)
        or contains_term(selected, value)
        for value in values
    )


# ---------------------------------------------------------
# GENDER HANDLING
# ---------------------------------------------------------

def normalise_gender(value):
    value = normalise(value)

    if value in ["man", "male", "men", "boy", "boys"]:
        return "men"

    if value in ["woman", "female", "women", "girl", "girls"]:
        return "women"

    # Unisex and unknown are deliberately not accepted.
    return ""


def infer_gender_from_description(description):
    """
    Use the product description only when the structured gender
    field is empty. Do not classify a product as unisex.
    """
    text = normalise(description)

    men_terms = [
        "for men",
        "for man",
        "men's",
        "mens",
        "male",
        "for boys",
        "for a boy"
    ]

    women_terms = [
        "for women",
        "for woman",
        "women's",
        "womens",
        "female",
        "for girls",
        "for a girl"
    ]

    men_found = any(term in text for term in men_terms)
    women_found = any(term in text for term in women_terms)

    if men_found and not women_found:
        return "men"

    if women_found and not men_found:
        return "women"

    return ""


def get_product_gender(product):
    """
    Structured recipient_gender is preferred.
    Description is a fallback only when that field is blank.
    """
    gender = normalise_gender(product.get("recipient_gender"))

    if gender:
        return gender

    return infer_gender_from_description(product.get("description"))


def filter_by_gender(products, recipient_gender):
    requested_gender = normalise_gender(recipient_gender)

    if not requested_gender:
        return products.iloc[0:0].copy()

    if products.empty:
        return products.copy()

    mask = products.apply(
        lambda row: get_product_gender(row.to_dict()) == requested_gender,
        axis=1
    )

    return products[mask].copy()


# ---------------------------------------------------------
# LOAD PRODUCTS DIRECTLY FROM MYSQL
# ---------------------------------------------------------

def load_products():
    """
    Read all active, priced products from the live MySQL database.
    New products added by the admin are automatically included.
    """

    connection = get_connection()

    try:
        with connection.cursor() as cursor:
            cursor.execute(
                """
                SELECT
                    p.product_id,
                    p.product_name AS name,
                    c.category_name AS category,
                    p.brand,
                    p.description,
                    p.price,
                    p.image_path AS image,
                    p.recipient_gender,
                    p.interests,
                    p.occasions,
                    p.relationship,
                    p.recipient_age,
                    p.personality,
                    p.style,
                    p.gift_type
                FROM products p
                LEFT JOIN categories c
                    ON p.category_id = c.category_id
                WHERE
                    p.is_active = 1
                    AND p.price IS NOT NULL
                    AND p.price > 0
                    AND p.image_path IS NOT NULL
                    AND p.image_path != ''
                ORDER BY p.product_id DESC
                """
            )

            rows = cursor.fetchall()

    finally:
        connection.close()

    products = pd.DataFrame(rows)

    if products.empty:
        return products

    products["price"] = pd.to_numeric(
        products["price"],
        errors="coerce"
    )

    products = products.dropna(subset=["price"])
    products = products[products["price"] > 0].copy()

    # Only keep products whose image file exists.
    def image_exists(image_path):
        if not image_path:
            return False

        relative_path = str(image_path).replace("\\", "/").lstrip("/")

        full_path = PROJECT_ROOT / relative_path

        return full_path.is_file()

    products = products[
        products["image"].apply(image_exists)
    ].copy()

    products["gender_for_matching"] = products.apply(
        lambda row: get_product_gender(row.to_dict()),
        axis=1
    )

    return products.reset_index(drop=True)


# ---------------------------------------------------------
# DESCRIPTION AND PREFERENCE MATCHING
# ---------------------------------------------------------

def product_search_text(product):
    """
    Combine product information into searchable text.
    The description helps the agent understand who a gift
    is suitable for and what interests it relates to.
    """
    fields = [
        product.get("name", ""),
        product.get("category", ""),
        product.get("brand", ""),
        product.get("description", ""),
        product.get("interests", ""),
        product.get("occasions", ""),
        product.get("relationship", ""),
        product.get("recipient_age", ""),
        product.get("personality", ""),
        product.get("style", ""),
        product.get("gift_type", "")
    ]

    return " ".join(
        normalise(value)
        for value in fields
        if value is not None
    )


def description_matches(product, selected_value):
    """
    Search the description and other product details for
    a preference that may not be repeated in the metadata.
    """
    selected = normalise(selected_value)

    if not selected:
        return True

    searchable_text = product_search_text(product)

    return contains_term(searchable_text, selected)


def preference_matches(product, field_name, selected_value):
    """
    First check the dedicated database field.
    If it does not match, check the product description
    and other descriptive details.
    """
    selected = normalise(selected_value)

    if not selected:
        return True

    field_value = product.get(field_name, "")

    if matches_preference(field_value, selected):
        return True

    return description_matches(product, selected)


# ---------------------------------------------------------
# MATCH SCORING
# ---------------------------------------------------------

def calculate_match(product, preferences):
    score = 0
    reasons = []

    scoring_fields = [
        ("interest", "interests", 5),
        ("occasion", "occasions", 4),
        ("relationship", "relationship", 2),
        ("recipient_age", "recipient_age", 2),
        ("personality", "personality", 2),
        ("style", "style", 2),
        ("gift_type", "gift_type", 2)
    ]

    for preference_name, product_field, points in scoring_fields:
        selected_value = preferences.get(preference_name)

        if not selected_value:
            continue

        if preference_matches(product, product_field, selected_value):
            score += points
            reasons.append(preference_name.replace("_", " ").title())

    # Description is useful for additional context.
    description = normalise(product.get("description"))

    if description:
        selected_terms = [
            preferences.get("interest"),
            preferences.get("occasion"),
            preferences.get("relationship"),
            preferences.get("recipient_age"),
            preferences.get("personality"),
            preferences.get("style"),
            preferences.get("gift_type")
        ]

        for term in selected_terms:
            if term and contains_term(description, term):
                score += 1

    return score, reasons


# ---------------------------------------------------------
# RECOMMEND GIFTS
# ---------------------------------------------------------

def recommend_gifts(
    relationship=None,
    occasion=None,
    interest=None,
    budget=None,
    recipient_gender=None,
    keywords=None,
    recipient_age=None,
    personality=None,
    style=None,
    gift_type=None,
    **kwargs
):
    """
    Return products that match:
    - Recipient gender
    - Budget
    - Interest
    - Occasion

    Other preferences improve the ranking.
    """

    try:
        budget = float(budget)
    except (TypeError, ValueError):
        return pd.DataFrame()

    if budget <= 0:
        return pd.DataFrame()

    requested_gender = normalise_gender(recipient_gender)

    if not requested_gender:
        return pd.DataFrame()

    preferences = {
        "interest": interest,
        "occasion": occasion,
        "relationship": relationship,
        "recipient_age": recipient_age,
        "personality": personality,
        "style": style,
        "gift_type": gift_type
    }

    products = load_products()

    if products.empty:
        return products

    # Strict gender filtering. No unisex products.
    products = filter_by_gender(products, requested_gender)

    if products.empty:
        return products

    # Never recommend a product above the user's budget.
    products = products[
        products["price"].astype(float) <= budget
    ].copy()

    if products.empty:
        return products

    # Interest and occasion are required matching preferences.
    if not interest or not occasion:
        return pd.DataFrame()

    matching_rows = []

    for _, row in products.iterrows():
        product = row.to_dict()

        interest_match = preference_matches(
            product,
            "interests",
            interest
        )

        occasion_match = preference_matches(
            product,
            "occasions",
            occasion
        )

        # Do not recommend unrelated products just because
        # they are within the budget.
        if not interest_match or not occasion_match:
            continue

        score, reasons = calculate_match(product, preferences)

        product["match_score"] = score
        product["match_reasons"] = reasons

        matching_rows.append(product)

    if not matching_rows:
        return pd.DataFrame()

    recommendations = pd.DataFrame(matching_rows)

    recommendations = recommendations.sort_values(
        by=["match_score", "price"],
        ascending=[False, True]
    )

    return recommendations.reset_index(drop=True)


# ---------------------------------------------------------
# OTHER MATCHING PRODUCTS
# ---------------------------------------------------------

def get_other_products(
    budget=None,
    recipient_gender=None,
    exclude_ids=None,
    limit=4,
    **kwargs
):
    """
    Return additional products for the same recipient,
    within budget and with valid images and prices.
    """

    try:
        budget = float(budget)
    except (TypeError, ValueError):
        return pd.DataFrame()

    requested_gender = normalise_gender(recipient_gender)

    if budget <= 0 or not requested_gender:
        return pd.DataFrame()

    products = load_products()

    if products.empty:
        return products

    products = filter_by_gender(products, requested_gender)

    products = products[
        products["price"].astype(float) <= budget
    ].copy()

    if exclude_ids is not None:
        if not isinstance(exclude_ids, (list, tuple, set)):
            exclude_ids = [exclude_ids]

        excluded = {str(item) for item in exclude_ids}

        products = products[
            ~products["product_id"].astype(str).isin(excluded)
        ].copy()

    if products.empty:
        return products

    return products.sort_values(
        by="price",
        ascending=True
    ).head(int(limit)).reset_index(drop=True)
