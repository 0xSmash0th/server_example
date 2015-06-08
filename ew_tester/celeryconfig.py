
BROKER_URL = "redis://ew_tb_was_3p"
CELERY_RESULT_BACKEND = "redis://ew_tb_was_3p"

CELERY_RESULT_SERIALIZER = 'json'
CELERY_TASK_SERIALIZER = 'json'
CELERY_TASK_RESULT_EXPIRES = 3600

CELERY_IMPORTS = ['queen_of_bots', 'bot']

