FROM python:3.10-slim

WORKDIR /app

RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir rasa mysql-connector-python

RUN useradd -m -u 1000 user
COPY --chown=user:user . /app

RUN sed -i 's/\r$//' start.sh && chmod +x start.sh

USER user
RUN rasa train

EXPOSE 7860
CMD ["bash", "start.sh"]
