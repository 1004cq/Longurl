import os, tempfile, unittest
os.environ['DATABASE_URL'] = 'sqlite:///' + os.path.join(tempfile.gettempdir(), 'eeee-python-test.db')
os.environ['API_TOKEN'] = 'test-token'
import server

class Smoke(unittest.TestCase):
    def test_public_url_and_allocation(self):
        result = server.create_link('example.com/test', 20)
        self.assertTrue(result['url'].endswith('e' * result['length']))
        self.assertEqual(result['target'], 'https://example.com/test')
    def test_private_url_rejected(self):
        with self.assertRaises(ValueError): server.create_link('http://127.0.0.1/test', 20)

if __name__ == '__main__': unittest.main()
