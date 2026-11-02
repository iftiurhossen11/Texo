from __future__ import annotations

import sys
import types
import unittest
from unittest.mock import Mock, patch

from config import settings
from services import cloudflare_embeddings, pg_vector_store


class CloudflareEmbeddingsTests(unittest.TestCase):
    def test_cloudflare_response_is_validated_and_returned(self) -> None:
        vector = [0.25] * 768
        response = Mock()
        response.json.return_value = {"result": {"data": [vector]}}

        with (
            patch.object(settings, "cloudflare_account_id", "test-account"),
            patch.object(settings, "cloudflare_api_token", "test-token"),
            patch("services.cloudflare_embeddings.requests.post", return_value=response) as post,
        ):
            result = cloudflare_embeddings.embed_query("factory hours")

        self.assertEqual(result, vector)
        self.assertEqual(post.call_args.kwargs["json"], {"text": ["factory hours"]})
        self.assertEqual(
            post.call_args.kwargs["headers"]["Authorization"],
            "Bearer test-token",
        )

    def test_cloudflare_credentials_are_required(self) -> None:
        with (
            patch.object(settings, "cloudflare_account_id", None),
            patch.object(settings, "cloudflare_api_token", None),
            self.assertRaisesRegex(RuntimeError, "credentials are not configured"),
        ):
            cloudflare_embeddings.embed_query("query")


class PostgresVectorStoreTests(unittest.TestCase):
    def test_workspace_and_department_filters_are_in_the_database_query(self) -> None:
        vector_text = "[" + ",".join(["0.0"] * 768) + "]"

        class FakeCursor:
            query = ""
            parameters = ()

            def __enter__(self):
                return self

            def __exit__(self, *_args):
                return False

            def execute(self, query, parameters):
                self.query = query
                self.parameters = parameters

            def fetchall(self):
                return [("doc_1_chunk_0", "content", 1, 0, None, 1, "sop", 0.9)]

        class FakeConnection:
            def __init__(self, cursor):
                self._cursor = cursor

            def __enter__(self):
                return self

            def __exit__(self, *_args):
                return False

            def cursor(self):
                return self._cursor

        cursor = FakeCursor()
        psycopg = types.ModuleType("psycopg")
        psycopg.connect = Mock(return_value=FakeConnection(cursor))

        with (
            patch.object(settings, "database_url", "postgresql://test"),
            patch.dict(sys.modules, {"psycopg": psycopg}),
        ):
            results = pg_vector_store.query_similar(
                query_embedding=[0.0] * 768,
                workspace_id=7,
                top_k=3,
                doc_type_filter="sop",
                document_id_filter=1,
                allowed_departments=["hr_training", "general"],
            )

        self.assertIn("doc_type = %s", cursor.query)
        self.assertIn("workspace_id = %s", cursor.query)
        self.assertIn("document_id = %s", cursor.query)
        self.assertIn("department = ANY(%s)", cursor.query)
        self.assertEqual(
            cursor.parameters,
            [
                vector_text,
                7,
                "sop",
                1,
                ["hr_training", "general"],
                vector_text,
                3,
            ],
        )
        self.assertEqual(results[0]["document_id"], 1)

    def test_empty_department_allowlist_returns_no_results(self) -> None:
        with patch("services.pg_vector_store._connect") as connect:
            results = pg_vector_store.query_similar(
                query_embedding=[0.0] * 768,
                workspace_id=7,
                top_k=3,
                allowed_departments=[],
            )

        self.assertEqual(results, [])
        connect.assert_not_called()

    def test_embedding_dimension_is_validated(self) -> None:
        with self.assertRaisesRegex(ValueError, "768-dimension"):
            pg_vector_store._as_vector([0.0])


if __name__ == "__main__":
    unittest.main()
