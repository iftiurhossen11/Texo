from __future__ import annotations

import unittest
from unittest.mock import patch

from services import vector_store


class FakeCollection:
    def __init__(self) -> None:
        self.query_kwargs = None
        self.upsert_kwargs = None
        self.delete_kwargs = None

    def query(self, **kwargs):
        self.query_kwargs = kwargs
        return {"ids": [[]], "documents": [[]], "metadatas": [[]], "distances": [[]]}

    def upsert(self, **kwargs):
        self.upsert_kwargs = kwargs

    def get(self, **kwargs):
        self.delete_kwargs = kwargs
        return {"ids": []}


class ChromaWorkspaceIsolationTests(unittest.TestCase):
    def setUp(self) -> None:
        self.collection = FakeCollection()
        self.backend_patch = patch.object(vector_store.settings, "vector_store_backend", "chroma")
        self.backend_patch.start()
        self.collection_patch = patch.object(vector_store, "get_collection", return_value=self.collection)
        self.collection_patch.start()

    def tearDown(self) -> None:
        self.collection_patch.stop()
        self.backend_patch.stop()

    def test_query_always_filters_by_workspace(self) -> None:
        vector_store.query_similar(
            query_embedding=[0.1],
            workspace_id=9,
            top_k=3,
            allowed_departments=["qa"],
        )

        self.assertEqual(
            self.collection.query_kwargs["where"],
            {"$and": [{"workspace_id": 9}, {"department": {"$in": ["qa"]}}]},
        )

    def test_upsert_and_delete_include_workspace_identity(self) -> None:
        vector_store.upsert_chunks(
            document_id=20,
            workspace_id=9,
            doc_type="sop",
            department="qa",
            chunks=[{"chunk_index": 0, "content": "QA instructions"}],
            embeddings=[[0.1]],
        )
        self.assertEqual(self.collection.upsert_kwargs["metadatas"][0]["workspace_id"], 9)

        vector_store.delete_by_document_id(document_id=20, workspace_id=9)
        self.assertEqual(
            self.collection.delete_kwargs["where"],
            {"$and": [{"document_id": 20}, {"workspace_id": 9}]},
        )

    def test_legacy_vectors_are_assigned_to_the_initial_workspace(self) -> None:
        class LegacyCollection:
            def __init__(self) -> None:
                self.updates = []

            def count(self):
                return 2

            def get(self, limit, offset, include):
                if offset > 0:
                    return {"ids": [], "metadatas": []}
                return {
                    "ids": ["legacy-1", "legacy-2"],
                    "metadatas": [{"document_id": 1}, {"document_id": 2}],
                }

            def update(self, **kwargs):
                self.updates.append(kwargs)

        legacy_collection = LegacyCollection()
        vector_store._migrate_legacy_workspace_metadata(legacy_collection)

        self.assertEqual(len(legacy_collection.updates), 1)
        self.assertEqual(
            legacy_collection.updates[0]["metadatas"],
            [{"document_id": 1, "workspace_id": 1}, {"document_id": 2, "workspace_id": 1}],
        )


if __name__ == "__main__":
    unittest.main()
