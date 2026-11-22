
import os

from flask import Flask, request, jsonify
from recommend import recommend_gifts, get_other_products

app = Flask(__name__)


@app.route("/recommend", methods=["POST"])
def get_recommendations():

    data = request.get_json(silent=True) or {}

    required = ["relationship", "occasion", "interest", "budget"]

    for field in required:
        if data.get(field) in (None, ""):
            return jsonify({
                "success": False,
                "message": f"Please provide {field}."
            }), 400

    try:
        budget = float(data["budget"])

        if budget <= 0:
            raise ValueError

    except (TypeError, ValueError):
        return jsonify({
            "success": False,
            "message": "Please enter a valid budget amount."
        }), 400

    try:
        recipient_gender = data.get("recipient_gender", "")

        results = recommend_gifts(
            relationship=data.get("relationship", ""),
            occasion=data.get("occasion", ""),
            interest=data.get("interest", ""),
            budget=budget,
            recipient_age=data.get("recipient_age", ""),
            personality=data.get("personality", ""),
            style=data.get("style", ""),
            gift_type=data.get("gift_type", ""),
            extra_notes=data.get("extra_notes", ""),
            recipient_gender=recipient_gender
        )

        recommendations = []

        for _, product in results.head(8).iterrows():

            match_reasons = product.get("match_reasons", [])

            recommendations.append({
                "product_id": int(product["product_id"]),
                "name": str(product["name"]),
                "category": str(product["category"]),
                "price": float(product["price"]),
                "match_score": int(product.get("match_score", 0)),
                "match_reason": (
                    ", ".join(match_reasons)
                    if isinstance(match_reasons, list) and match_reasons
                    else "Matches your selected gift preferences and budget."
                ),
                "recommendation_type": "Recommended Gift"
            })

        recommended_ids = [
            product["product_id"]
            for product in recommendations
        ]

        other_results = get_other_products(
            exclude_ids=recommended_ids,
            relationship=data.get("relationship", ""),
            recipient_gender=recipient_gender,
            budget=budget
        )

        other_products = []

        for _, product in other_results.head(8).iterrows():

            other_products.append({
                "product_id": int(product["product_id"]),
                "name": str(product["name"]),
                "category": str(product["category"]),
                "price": float(product["price"])
            })

        return jsonify({
            "success": True,
            "budget": budget,
            "recommendations": recommendations,
            "other_products": other_products,
            "message": (
                "Recommendations found."
                if recommendations
                else "No gifts match all your selected preferences and budget. Please try a different interest or increase your budget."
            )
        })

    except Exception as error:

        app.logger.exception("Gift Finder error")

        return jsonify({
            "success": False,
            "message": "Something went wrong while finding gifts. Please try again."
        }), 500


if __name__ == "__main__":

    print("Giftly Recommendation API is running...")

    app.run(
        host=os.environ.get("GIFTLY_API_HOST", "127.0.0.1"),
        port=int(os.environ.get("GIFTLY_API_PORT", "5000")),
        debug=False
    )
